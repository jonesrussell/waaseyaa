# Mission: SqlEntityQuery Access Checking

**Status:** Ready for /spec-kitty.plan
**Change mode:** `code_change`
**Refs:** issue #1495 (decision: ship the FR), `docs/specs/access-control.md`, `docs/specs/field-access.md`, ADR-019 (MCP tool access enforcement)

## Why this mission exists

`packages/entity-storage/src/SqlEntityQuery.php` currently carries this stub:

```
// No-op in v0.1.0 — access checking is not implemented yet.
```

The class is live — `SqlEntityStorage:1161` returns `new SqlEntityQuery(...)` — and is consumed across `packages/graphql`, `packages/listing`, and `packages/entity-storage` itself. Every entity query that flows through this surface is currently returning rows **without** consulting the per-entity access policy. That is a security gap for any deployment that relies on entity-level access policies to keep callers from seeing data they shouldn't.

The gap matters more after recent mission outcomes locked the surrounding posture:

- **Agent runtime** (mission `agent-executor-01KRWPK7`) — agents run as the initiator's account; every tool call enforces entity-level access against that account (ADR-019). If an agent's `entity.search` tool ultimately runs an `SqlEntityQuery` that returns un-filtered rows, the access posture is undermined at the deepest layer.
- **MCP tool unification** — the same path serves admin SPA listings, GraphQL resolvers, and external MCP clients. A query-level bypass is reachable from every surface.
- **Admin SPA roadmap** — operator-facing listings must show only what the operator is authorized to see; that promise can only be kept if the query layer filters.
- **Enterprise readiness** — leaving a stub in a load-bearing query path is incompatible with the v1 security stance for the framework.

This mission ships the FR: a real access check at the query layer, with parity to the semantics already implemented in `EntityStorage::loadMultiple()`.

## User scenarios & testing

### Primary flow — listing rendered for a non-admin account

1. Operator Daisy holds `view_node` for nodes she authored but not for nodes others authored. She opens the admin SPA's node listing.
2. The admin surface calls `EntityRepository::query()`, which constructs an `SqlEntityQuery`. The query executes and pages 25 candidate rows.
3. Before returning, the query batches each candidate through `AccessChecker::check('view', ...)`. Rows whose result is `forbidden` are dropped; rows whose result is `allowed` or `neutral` (open-by-default at the entity level — see `docs/specs/access-control.md` § "Access result semantics") are kept.
4. The listing returns the filtered page to the SPA. Total count reflects the post-filter cardinality.

### Agent flow — `entity.search` tool

1. Agent runs `entity.search(type: 'node', filters: [...])` as initiator account.
2. The tool dispatches to `EntityRepository::query()`. The query enforces the same `view`-action access check against the initiator's account.
3. Only rows the initiator can view are returned to the LLM. The tool never leaks IDs / labels of entities the initiator can't see — the audit row records the resolved filter, not the dropped-row count.

### GraphQL flow — entity-collection resolver

1. GraphQL resolver receives `nodes(filter: {...})`. The resolver runs the same query path.
2. The user's account (resolved via JWT / session) is passed in. Forbidden rows are dropped before the GraphQL response is serialized.

### System flow — bypass mode

1. A scheduled job that needs to inspect every row (e.g. the stalled-run reaper, the purge job, a migration) creates an `SqlEntityQuery` and calls `accessCheck(false)`.
2. The query skips the per-row access check. This is the SAME contract as `EntityStorage::loadMultiple()`'s existing flag — an explicit, audited opt-out for system contexts.

### Reliability flow — anonymous account on a public surface

1. An anonymous request hits a GraphQL endpoint that exposes published nodes. The resolver passes `AnonymousUser` (id=0) to the query.
2. The query runs the same access check against `AnonymousUser`. Anonymous holds only the access granted to anonymous; the response reflects that filter.

### Edge cases

- Empty result page from filter: the query returns an empty collection rather than re-paging until the page fills (parity with `loadMultiple()`).
- Cardinality + pagination interaction: when callers ask for a count, the count is the **post-filter** cardinality. Callers needing the unfiltered count must use `accessCheck(false)` explicitly.
- Mixed allow / deny across a page: the page returns only allowed rows; the next-page cursor still advances by the unfiltered candidate window so we don't re-scan.
- Account null: when no account is bound, the query refuses to execute unless `accessCheck(false)` is set. (Hardens against accidental anonymous bypass; mirrors the controller-level requirement to read `_account`.)
- Bulk fetch (`saveMany`-shape callers): the access check still runs per-row, but the batching strategy uses one `AccessChecker::checkMultiple()` call per page so we don't N+1.
- Field-level access (`FieldAccessPolicyInterface`) is **out of scope** for this mission — it runs later in the serialization pipeline. This mission addresses entity-level access at query time.

## Requirements

### Functional (FR)

| ID | Requirement | Status |
|---|---|---|
| FR-001 | `SqlEntityQuery::execute()` SHALL invoke `AccessChecker::check('view', $row, $account)` (or batched equivalent) for every candidate row before returning, except when `accessCheck(false)` is set. | Active |
| FR-002 | `SqlEntityQuery::accessCheck(bool $enabled)` SHALL toggle access enforcement. Default state SHALL be `true`. | Active |
| FR-003 | `SqlEntityQuery` SHALL accept an `AccountInterface` via constructor injection or a setter (`setAccount()`); when the access check is enabled and no account is bound, `execute()` SHALL throw `MissingQueryAccountException` rather than silently bypass. | Active |
| FR-004 | The query SHALL use `AccessChecker::checkMultiple()` (or equivalent batch API) to evaluate a full page of candidate rows in one pass, so we do not run N individual checks for an N-row page. | Active |
| FR-005 | `AccessResult::allowed()` and `AccessResult::neutral()` SHALL admit a row to the result set; `AccessResult::forbidden()` SHALL drop it. Semantics match `EntityStorage::loadMultiple()` entity-level filtering. | Active |
| FR-006 | Result counts returned by `SqlEntityQuery::count()` SHALL reflect post-filter cardinality when `accessCheck(true)` is set, and pre-filter cardinality when `accessCheck(false)` is set. | Active |
| FR-007 | Page cursors / offsets SHALL advance by the unfiltered candidate window per page, so callers requesting page N don't re-scan candidates already evaluated. | Active |
| FR-008 | `SqlEntityStorage::loadMultiple()` SHALL be inspected for any place that could bypass the new query-level check; if a code path constructs `SqlEntityQuery` and forgets to bind the account, it SHALL be refactored to thread the initiator's account through. | Active |
| FR-009 | The legacy comment `// No-op in v0.1.0 — access checking is not implemented yet.` in `SqlEntityQuery` SHALL be removed once the implementation lands. | Active |
| FR-010 | A new exception class `MissingQueryAccountException` SHALL live alongside other entity-storage exceptions and be thrown when access is enabled but no account is bound. | Active |

### Non-functional (NFR)

| ID | Requirement | Measurable threshold | Status |
|---|---|---|---|
| NFR-001 | Adding access checking SHALL NOT introduce more than O(1) additional database queries per `SqlEntityQuery::execute()` call. | Per-page batch via `AccessChecker::checkMultiple()`; integration test asserts query count is bounded. | Active |
| NFR-002 | Per-page latency overhead SHALL be measurable but bounded. | A 25-row page check against a real `AgentRunAccessPolicy`-shape policy completes in **≤ 100 ms** wall-clock on SQLite in the test harness. | Active |
| NFR-003 | Existing call sites SHALL NOT regress in behaviour. | All `composer test` suites that currently exercise SqlEntityQuery green; new tests added for the access-check paths green. | Active |
| NFR-004 | Layer enforcement SHALL pass. | `bin/check-package-layers` exits 0. `packages/entity-storage` (Layer 1) can import from `packages/access` (Layer 1) — same-layer import, allowed. | Active |
| NFR-005 | Dead-code gate SHALL pass. | `bin/check-dead-code` reports no new findings beyond the existing baseline. | Active |
| NFR-006 | Static analysis SHALL pass at level 5. | `composer phpstan` exits 0. | Active |
| NFR-007 | Code style SHALL pass. | `composer cs-check` exits 0. | Active |
| NFR-008 | Composer policy gate SHALL pass. | `bin/check-composer-policy` exits 0. No new `waaseyaa/*` deps unless required by the implementation; if required, pinned to `^<current-tag>`. | Active |

### Constraints (C)

| ID | Constraint | Status |
|---|---|---|
| C-001 | PHP 8.5+ across the package. `declare(strict_types=1)` in every file. | Active |
| C-002 | The mission SHALL NOT introduce field-level access enforcement at the query layer — `FieldAccessPolicyInterface` runs later in the serialization pipeline (per `docs/specs/field-access.md`). Query layer filters at entity granularity only. | Active |
| C-003 | The mission SHALL use the existing `AccessChecker` + `AccessPolicyInterface` pipeline. No new policy interface, no new dispatch surface. | Active |
| C-004 | `accessCheck(false)` is preserved as an explicit, named, audited bypass — not removed. System contexts (reaper, purge, migrations) depend on it. The contract MUST match the `EntityStorage::loadMultiple()` bypass semantics already in use. | Active |
| C-005 | Logging via `Waaseyaa\Foundation\Log\LoggerInterface`. Constructors accept `?LoggerInterface $logger = null` and default to `NullLogger`. | Active |
| C-006 | Account null + `accessCheck(true)` SHALL throw, not silently bypass. This is the security-critical default. | Active |
| C-007 | No raw PDO. All persistence flows through the canonical entity-storage pipeline per `.claude/rules/entity-storage-invariant.md`. | Active |

## Success Criteria

| ID | Criterion |
|---|---|
| SC-001 | A non-admin user listing entities through GraphQL, the admin SPA, or an agent tool sees only entities for which their account is permitted `view` access. |
| SC-002 | An admin user with `bypass_ownership`-style capability sees the full set, exercised via the same query path. |
| SC-003 | A system context that explicitly calls `accessCheck(false)` continues to see all rows for purposes like reaping, purging, and migrations. |
| SC-004 | An anonymous request sees only rows visible to `AnonymousUser`. |
| SC-005 | Removing or commenting out the access policy class for a given entity type results in zero rows returned for a non-bypass account (deny-by-default at policy level) — confirming the query honours `forbidden` results. |
| SC-006 | No call site that previously worked with `SqlEntityQuery` regresses; every existing test green. |
| SC-007 | Per-page wall-clock overhead measured at < 100 ms on the SQLite test harness for a 25-row page (NFR-002). |
| SC-008 | Framework health gates (cs-check, phpstan, layers, dead-code, composer-policy) all green on the final PR. |

## Key Entities

- **`SqlEntityQuery`** — the existing query builder in `packages/entity-storage/src/SqlEntityQuery.php`. Gains `setAccount()`, `accessCheck(bool)`, and per-row filtering.
- **`AccessChecker`** — existing access pipeline in `packages/access`. Provides `check()` (single row) and `checkMultiple()` (batched). Mission consumes; does not modify the contract.
- **`AccessResult`** — existing VO with `allowed / neutral / forbidden` cases. Used as-is.
- **`MissingQueryAccountException`** — NEW exception under `packages/entity-storage/src/Exception/` thrown when access is enabled but no account is bound.
- **`AccountInterface`** — existing contract from `packages/access`. The query stores it via a nullable property; no `instanceof` on concrete types.

## Bulk-Edit Classification

**Not applicable.** This mission introduces new behaviour in `SqlEntityQuery` and a new exception class. It does not rename existing cross-cutting symbols. `change_mode: code_change`.

## Assumptions

- `AccessChecker::checkMultiple()` exists or can be added cheaply (it's the documented batch entry point). If not, the implementation may need a small extension in `packages/access`. If that extension is non-trivial, escalate before the implement gate opens.
- `packages/access` is at Layer 1 (per CLAUDE.md), as is `packages/entity-storage`. Same-layer imports are allowed; no layer rule changes needed.
- The existing `EntityStorage::loadMultiple()` access-check shape (whatever form it takes today — gate at fetch time, batch lookup, etc.) is the canonical reference. Implementation mirrors it.
- The query-layer filter is sufficient for v1; downstream serialization-layer access (field-level redaction) is enforced separately in `EntityAccessHandler` and remains out of scope here.
- Production deployments depending on the current stub behaviour (i.e. expecting to see all rows without auth checks) do not exist — internal verification in implementation will confirm via a repo-wide grep for `accessCheck(false)` and `SqlEntityQuery` constructor invocation patterns.

## Dependencies

**Upstream packages relied on (unchanged):**
- `packages/access` — `AccessChecker`, `AccessResult`, `AccessPolicyInterface`, `AccountInterface`.
- `packages/foundation` — `LoggerInterface`.
- `packages/entity` — `EntityInterface`, `EntityTypeInterface`.

**Downstream consumers (covered by integration tests):**
- `packages/graphql` — entity-collection resolvers.
- `packages/listing` — listing pipeline (`docs/specs/listing-pipeline-v1.md`).
- `packages/ai-tools` — `EntitySearchTool`, `EntityListTool` (the agent runtime path that motivated this mission).
- `packages/api` — `JsonApiController` index endpoints (indirectly via `EntityRepository::query()`).

## Scope

### In scope (v1)

- All FRs, NFRs, and Cs above.
- New `MissingQueryAccountException`.
- Stub-removal in `SqlEntityQuery`.
- Test matrix per the spec: allow / deny / mixed / bypass / anonymous / admin.
- Integration test against `DBALDatabase::createSqlite()` proving filtering across GraphQL, listing, and admin-SPA-shaped queries.

### Out of scope (v1.x or later)

- Field-level access at query time (lives in serialization pipeline — `EntityAccessHandler` / `FieldAccessPolicyInterface`).
- Pre-filter pushdown into SQL (e.g. encoding the access policy as a WHERE clause) — possible v1.x optimization for hot policies; query-time row evaluation is the v1 contract.
- Reworking the existing `EntityStorage::loadMultiple()` shape — this mission consumes its semantics and mirrors them, but does not refactor that path.
- Cross-tenant query scoping — separate concern, separate mission.
- Admin SPA visualization of "access-filtered" vs raw counts — UI work for a follow-up.

## WP outline (for `/spec-kitty.tasks-outline`)

| WP | Title | Net change |
|---|---|---|
| WP-01 | `MissingQueryAccountException` + `SqlEntityQuery` signature additions | New exception class; `setAccount()`, `accessCheck(bool)` methods; constructor doesn't break existing call sites (defaults to `accessCheck(true)` with later bind). |
| WP-02 | Per-row filter + batched `AccessChecker::checkMultiple()` call | Core enforcement in `execute()`; honor `AccessResult::forbidden` to drop rows; honor allowed / neutral to keep. |
| WP-03 | Account-binding sweep across call sites | Audit every constructor of `SqlEntityQuery` in `packages/graphql`, `packages/listing`, `packages/api`, `packages/entity-storage`, `packages/ai-tools`; thread `$account` from `_account` request attribute / agent initiator down to the query. |
| WP-04 | `count()` + cursor parity | Implement post-filter cardinality and unfiltered-window pagination per FR-006 / FR-007. |
| WP-05 | Test matrix + integration tests | Allow / deny / mixed / bypass / anonymous / admin. Wire through GraphQL + listing + ai-tools' `EntitySearchTool`. |
| WP-06 | Stub removal + docs | Delete the stub comment; refresh `docs/specs/access-control.md` and any spec that references the query-layer gap. |

WP-01 → WP-02 → WP-03 → WP-04 are the critical path. WP-05 is partially parallelizable with WP-03 once the contract is stable. WP-06 is the wrap-up.

## Outstanding work for /spec-kitty.plan

- Confirm `AccessChecker::checkMultiple()` exists or scope a small extension in `packages/access`.
- Confirm `EntityStorage::loadMultiple()` access-check semantics by reading the current code; mirror exactly.
- Enumerate every `new SqlEntityQuery(...)` and `EntityRepository::query()` call site for the WP-03 sweep.
- Decide whether `setAccount()` is the only binding mechanism or whether a constructor-time injection is also offered for ergonomics.

## References

- Issue #1495 — owner's decision recorded inline.
- `docs/specs/access-control.md` — canonical access-pipeline contract.
- `docs/specs/field-access.md` — field-level access (out of scope for this mission).
- `docs/specs/agent-executor.md` § "Access enforcement" — agent runtime's dependency on this filter.
- `docs/adr/019-mcp-tool-access-enforcement.md` — MCP tool path's dependency on this filter.
- `.claude/rules/entity-storage-invariant.md` — canonical entity persistence pipeline.
- `packages/entity-storage/src/SqlEntityQuery.php:1` and `SqlEntityStorage.php:1161` — the surface this mission touches.

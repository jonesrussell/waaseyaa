# Implementation Plan: SqlEntityQuery Access Checking

**Branch:** `main` (planning + merge target)
**Date:** 2026-05-18
**Spec:** [spec.md](spec.md)
**Mission ID:** `01KRYP1581AWTRZHQ14XR7TNJ4`

## Summary

Replace `SqlEntityQuery::accessCheck()`'s v0.1.0 no-op stub with a real
per-row access filter that consults the existing
`EntityAccessHandler::check()` pipeline. Default state is `true`. The
existing `accessCheck(false)` opt-out is preserved as an explicit
system-context bypass. `execute()` returns access-filtered entity IDs
(preserving `EntityQueryInterface::execute(): array` contract). A new
`MissingQueryAccountException` fails closed when access is enabled but
no account is bound. The mission sweeps every `getQuery()` consumer
across the framework to thread the initiator account through and
audits each existing `accessCheck(false)` for justification.

## Technical Context

**Language/Version:** PHP 8.5+

**Primary dependencies:**
- `waaseyaa/access` — `EntityAccessHandler`, `AccessPolicyInterface`, `AccessResult`, `AccountInterface`
- `waaseyaa/entity` — `EntityQueryInterface`, `EntityInterface`
- `waaseyaa/entity-storage` — `SqlEntityQuery`, `SqlEntityStorage`
- `waaseyaa/foundation` — `LoggerInterface`

**Storage:** unchanged — SQLite (dev/tests), MySQL/PostgreSQL via DBAL. The mission introduces no new tables.

**Testing:** PHPUnit 10.5. Unit + Contract + Integration. `DBALDatabase::createSqlite()` for the integration harness.

**Performance Goals (from spec NFRs):**
- NFR-001: O(1) extra DB queries per `execute()`. The access check is in-memory unless individual policies hit the DB (which they currently do not).
- NFR-002: 25-row page check ≤ 100 ms wall-clock on the SQLite test harness.

**Constraints:**
- C-001 PHP 8.5+, `declare(strict_types=1)`
- C-002 No field-level access at query layer (lives in serializer)
- C-003 Reuse existing `AccessPolicyInterface` + `EntityAccessHandler`; no new policy contract
- C-004 `accessCheck(false)` preserved as explicit bypass
- C-006 Account null + `accessCheck(true)` throws
- C-007 No raw PDO; canonical entity-storage pipeline

**Scope/scale:**
- Access policies are typically O(1) per row (in-memory account-id comparison + capability lookup). The per-row check is cheap; the dominant cost is hydrating each row enough to expose `entity_type_id` for policy dispatch.
- Initial call-site sweep enumerated in research.md R-004: 7 packages, ~20 call sites.

## Charter Check

*Gate: must pass before Phase 0. Re-checked after Phase 1.*

Charter template: `software-dev-default`. Active paradigm: domain-driven-design. Active directives: `DIR-001`, `DIR-002`, `DIR-003`.

| Gate | Pass? | Note |
|---|---|---|
| **DDD: bounded contexts** | ✅ | `entity-storage` owns query mechanics; `access` owns policy evaluation. This mission threads an interface call across the seam; no boundary violation. |
| **DDD: aggregates** | ✅ | Entity-level filtering at query time is consistent with the entity-as-aggregate model (whole entities admit or deny; field-level filtering is a separate concern). |
| **DDD: ubiquitous language** | ✅ | Terms (`access`, `view`, `allow`, `forbid`, `bypass`) already in use across access subsystem. No new terminology. |
| **Testing standards** | ✅ | Unit, contract, integration coverage layered per Waaseyaa convention. |
| **Quality gates** | ✅ | cs-check, phpstan, layers, dead-code, composer-policy all required per NFRs. |
| **Performance benchmarks** | ✅ | NFR-002 carries a measurable threshold. |
| **Branch strategy** | ✅ | `branch_matches_target: true`; planning on `main`, merge to `main`. |
| **DIR-001/002/003** | ✅ | No conflicts identified. |

No violations to track.

## Engineering Alignment

Phase 0 code archaeology (`research.md`) busted four spec assumptions and locked the implementation contract:

1. **The right entity-level access surface is `EntityAccessHandler::check()`, NOT `AccessChecker::check()`**. `AccessChecker` is route-oriented (`check(Route, Account)`); `EntityAccessHandler` is entity-oriented (`check(EntityInterface, string $operation, AccountInterface, ?array $context = null)`). The spec referenced the wrong class.

2. **No batch API exists today**. `EntityAccessHandler` has only `check()` (single row), `checkCreateAccess()`, `checkFieldAccess()`, and `filterFields()`. Per R-001, we'll use per-row `check()` in a loop — the overhead is negligible because policies are in-memory; a batch helper is a v1.x optimization for hot policies, not v1 scope.

3. **`EntityStorage::loadMultiple()` has NO existing access check to mirror.** The spec's "parity with `loadMultiple()`" framing was based on a wrong premise. There is no precedent in the framework; this mission *establishes* entity-level access enforcement at the query layer.

4. **The call-site sweep is broader than the spec listed and includes existing explicit `accessCheck(false)` opt-outs.** Per R-004: 7 packages affected (oidc, relationship, ai-vector, graphql, genealogy, workflows, api), with at least 5 production `accessCheck(false)` invocations that need security review (some legitimate — `SemanticIndexWarmer`; some likely bugs — `SearchController`, `EntityResolver`).

Engineering decisions locked here:

| Decision | Choice | Rationale |
|---|---|---|
| Account binding mechanism | Setter (`SqlEntityQuery::setAccount(?AccountInterface)`) — NOT a constructor change | Constructor of `SqlEntityQuery` is called from one factory (`SqlEntityStorage::getQuery()`); adding to the constructor would force every storage subclass to thread the account. A setter is backward-compatible and leaves `getQuery()` callers free to bind the account once after retrieval. |
| `EntityQueryInterface` extension | Add `setAccount(?AccountInterface): static` to `EntityQueryInterface` so consumers can rely on the contract regardless of storage backend | Future non-SQL backends will need the same surface. |
| Per-row vs. batched check | Per-row `EntityAccessHandler::check()` in the post-execute loop | No batch API today; per-row is O(N) memory-bound. Future hot-policy batch optimization is non-blocking. |
| Filtered count semantics | `count()` returns the post-filter cardinality when `accessCheck(true)`; pre-filter when `accessCheck(false)` | Matches FR-006; matches what callers expect for paginators. |
| Hydration before access check | Yes — load entities to check, then return the IDs of survivors | `EntityAccessHandler::check()` takes `EntityInterface`, not IDs. `execute()` keeps its `: array` (of IDs) return type for backward compat. |
| `MissingQueryAccountException` placement | `packages/entity-storage/src/Exception/MissingQueryAccountException.php` | Same exception namespace as other entity-storage errors. |
| Account-null behaviour | `accessCheck(true)` + no account → throw | Per C-006; security-critical default. |
| Existing `accessCheck(false)` call sites | Audited individually in WP-03; documented (`security-review.md`) per call site as "justified" or "bug fix" | Some opt-outs are legitimate (`SemanticIndexWarmer`'s background index warm); others (`SearchController` user-facing search) are probably bugs the no-op stub masked. |

No NEEDS-CLARIFICATION markers remain. No additional research agents required.

## Project Structure

### Mission documents

```
kitty-specs/sql-entity-query-access-checking-01KRYP15/
├── spec.md                 # Mission spec (filed)
├── plan.md                 # This file
├── research.md             # Phase 0 output
├── data-model.md           # Phase 1 output
├── quickstart.md           # Phase 1 output
├── contracts/
│   └── entity-query-interface-additions.md  # Interface extension contract
├── checklists/
│   └── requirements.md     # Spec quality checklist (filed)
├── tasks/                  # /spec-kitty.tasks output (next phase)
├── meta.json               # Mission metadata
└── status.events.jsonl     # Runtime event log
```

### Source code (repository root)

```
packages/entity-storage/
├── src/
│   ├── SqlEntityQuery.php          # Major edit — gain setAccount(), real accessCheck(), per-row filter, post-filter count(), cursor parity
│   └── Exception/
│       └── MissingQueryAccountException.php  # NEW — thrown when check enabled, no account
├── tests/
│   └── Unit/
│       └── SqlEntityQueryAccessCheckTest.php  # NEW — allow/deny/mixed/bypass/anonymous/admin matrix

packages/entity/
└── src/
    └── Storage/
        └── EntityQueryInterface.php  # Edit — add setAccount(?AccountInterface): static

packages/access/
└── (unchanged — consumed only)

# Call-site sweep (WP-03) — at least 20 sites across 7 packages
packages/oidc/src/ClientRegistry/{OidcClientSeeder.php, OidcClientLookup.php}
packages/relationship/src/{RelationshipValidator.php, RelationshipDeleteGuardListener.php}
packages/ai-vector/src/{SemanticIndexWarmer.php, SearchController.php}
packages/graphql/src/Resolver/EntityResolver.php
packages/genealogy/src/{Ssr/GenealogySsrController.php, Service/GenealogyFamilyService.php, Service/GenealogyPedigreeService.php}
packages/workflows/src/DomainValidationListener.php
packages/api/src/JsonApiController.php

# (listing package consumers TBD by WP-03 sweep — initial grep showed 0 direct getQuery() calls in packages/listing/src/)

tests/Integration/PhaseN/EntityQueryAccessCheck/
├── ListingFilterTest.php
├── GraphQLResolverFilterTest.php
├── JsonApiIndexFilterTest.php
└── BypassRespectsSystemContextTest.php

docs/
└── security/
    └── sql-entity-query-access-check-bypass-audit.md   # WP-06 deliverable; line-by-line review of existing accessCheck(false) sites
```

**Structure decision:** No new packages. Edits across `packages/entity-storage` (primary), `packages/entity` (interface), and 7 downstream consumer packages. Bulk of risk is in the consumer sweep, not the core filter.

## Phase 0 — Research

See [research.md](research.md). All four outstanding research items resolved:

- R-001 Batch API → use per-row `EntityAccessHandler::check()`; defer batch helper
- R-002 `loadMultiple()` precedent → no existing access check; this mission establishes it
- R-003 Return type → keep `execute(): array` of IDs; hydrate-then-filter-then-return-IDs internally
- R-004 Call-site sweep → 7 packages, ~20 sites, 5 existing `accessCheck(false)` opt-outs needing audit

No NEEDS CLARIFICATION markers remain.

## Phase 1 — Design & Contracts

- **Data model:** [data-model.md](data-model.md). Documents `SqlEntityQuery`'s new internal state (account property, dispatcher), the `MissingQueryAccountException` shape, and the `EntityAccessHandler` consumption pattern.
- **Contracts:** [contracts/entity-query-interface-additions.md](contracts/entity-query-interface-additions.md). The `EntityQueryInterface::setAccount(?AccountInterface): static` addition, plus the formal `accessCheck` semantics that all backends MUST implement consistently.
- **Quickstart:** [quickstart.md](quickstart.md). Operator walkthrough — what callers see different after the mission lands.

## Bulk-Edit Plan

**Not applicable.** `change_mode: code_change`. The mission introduces new behaviour in `SqlEntityQuery` and a new exception class. It does not rename existing cross-cutting symbols. No `occurrence_map.yaml` required.

## Complexity Tracking

*No charter violations; no entries required.*

## Branch Contract (restated)

- Current branch at plan start: `main`
- Planning / base branch: `main`
- Final merge target: `main`
- `branch_matches_target`: `true`

## Next step

`/spec-kitty.tasks --mission sql-entity-query-access-checking-01KRYP15` to break the 6 WPs (per spec.md WP outline) into a task manifest.

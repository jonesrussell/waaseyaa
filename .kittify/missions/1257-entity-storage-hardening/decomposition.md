# Decomposition — entity-storage-hardening

Date: 2026-04-30 (Pass 2 WP01 output)

Mission charter: "Lock entity storage invariants: bundle subtables, FieldStorage::Data write/read symmetry, _data type coercion, shadow-collision guard, kernel-path integration test."

Anchor: `#1257` (open, user-flagged keep). Track 1 — Entity system & hydration.

---

## Mission summary

This is a **mechanical hardening sweep with one architectural cleavage embedded in it**. Seven of the eight survivor issues are tightening the seams of an existing, ratified pipeline — the entity-storage invariant in `.claude/rules/entity-storage-invariant.md` plus the bundle-subtable shape locked in `docs/specs/bundle-scoped-storage.md` and casting/hydration locked in `docs/specs/entity-system.md` §Casting & hydration. They want symmetry, layered diagnostics, and clearer DX errors. They do not propose new public interfaces, attributes, or events.

The eighth — **#1304** — is architectural: it asks to retire `HasCommunityInterface` as the tenancy opt-in mechanism and replace it with an `EntityType`-level declaration (or a separate `TenantScope` registration hook). That is a contract-shape change that ripples through `CommunityScope`, `SqlStorageDriver` wiring, every consumer's entity class, and a deprecation cycle for the marker. It cannot ship behind a "lock invariants" framing without acknowledgment.

**Mode: mechanical-with-one-architectural-asterisk.** Following the 1335 pattern for the seven hardening issues, with #1304 carved off as its own architectural WP that proposes one new EntityType key (`tenancy`) for ratification. NO-SPLIT — see §4.

---

## Absorbed issues + open anchor

All eight issues are **OPEN** at the time of this decomposition. Mission spec line "Each closed issue carries a cross-link comment pointing back to this mission" is **drift** (see §7) — the kill-list lifted them off the standalone-fix path and into this mission, but the issues themselves were not closed and no cross-link comment was posted.

| # | State | Title | Surface | Verdict |
|---|---|---|---|---|
| 1257 | OPEN (anchor, user-flagged keep) | fix(entity-query): _data integer fields fail string comparisons in SQLite | `SqlEntityQuery::condition()` value coercion against JSON-extracted numeric fields | Real correctness bug. Reproducible. WP-priority per kill list. |
| 1298 | OPEN | Centralize bundle-subtable name helper with `__` separator guard | `SqlSchemaHandler::bundleSubtableName()` already guards; `SqlEntityStorage::bundleSubtableName()` (private), `SqlEntityQuery::resolveField()` raw concat at lines 139, 370, and `SqlEntityStorage.php:672` do not | Internal helper consolidation. Live source confirms drift across three files. |
| 1299 | OPEN | Log warning when `load()` encounters a registered-field bundle with no subtable | `SqlEntityStorage::mergeBundleSubtableRow()` and `mergeBundleSubtableRowsBatch()` silent-skip when `bundleSubtableExists()` is false | Logging behavior, design call (recommendation: log once per `(entity_type, bundle)` via cache memoization). No new contract. |
| 1300 | OPEN | Extract `HealthChecker`'s entity/entity-storage dependencies to preserve layer discipline | `packages/foundation/src/Diagnostic/HealthChecker.php` imports from L1 (`Waaseyaa\Entity\*`, `Waaseyaa\EntityStorage\SqlSchemaHandler`, `Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface`) | Layer-graph violation. Issue offers three options; live source confirms imports verbatim. **Conflicts with `bin/check-package-layers`** if it actually scans foundation→entity edges. |
| 1301 | OPEN | Portable `ORPHAN_BUNDLE_SUBTABLE` detection for MySQL and PostgreSQL | `HealthChecker::findOrphanSubtables()` line 343 uses `sqlite_master` directly; non-SQLite path silently no-ops | Driver portability gap. Acceptance criteria are crisp. No new contract. |
| 1304 | OPEN | Move tenancy opt-in from `HasCommunityInterface` marker to `EntityType`/storage registration | `CommunityScope` consumes context, but the `is_a($entityType->getClass(), HasCommunityInterface::class, true)` opt-in check lives in service-provider wiring; framework-shipped `final` entities (e.g., `Waaseyaa\Groups\Group`) cannot be marked by consumers | **Architectural.** New `EntityType` key OR new tagged registration hook. Deprecation cycle for the marker. |
| 1308 | OPEN | entity-storage: symmetric query-side handling for `FieldStorage::Data` when legacy column exists | `SqlEntityStorage::splitForStorage()` (write) consults `getDataStoredCoreFieldNames()`; `SqlEntityQuery::resolveField()` (read) consults only `SchemaInterface::fieldExists()`. Asymmetry produces silently stale reads when a legacy column lingers after a field gains the storage hint | Real correctness bug. Mirrors the write-side helper into the query side. No new contract. |
| 1313 | OPEN, labeled `track-1-entity-system`, `dx` | Shadow-collision guard + clear duplicate-registration error | (A) `EntityTypeManager::addBundleFields()` should emit `LoggerInterface::notice()` when a bundle subtable doesn't yet exist. (B) `EntityTypeRegistrationCollisionException::duplicate` already exists at `EntityTypeManager.php:108` but the message must name BOTH registrants | DX hardening. No new contract. |

---

## Conventions to ratify

These are the conventions the mission codifies. They are the closest thing this mission has to "contract surfaces" — the spec must bless them explicitly so future readers don't read symmetry-fixes as license to invent new public types.

### K1 — Bundle subtable naming is a single helper

The `{base}__{bundle}` rule and the `__`-in-bundle-id guard belong to one shared helper consumed by `SqlEntityStorage`, `SqlEntityQuery`, and `SqlSchemaHandler`. Live source has the guard only in `SqlSchemaHandler::bundleSubtableName()` (line 127); the other three call-sites (storage:672, query:139, query:370, storage:201's argument-side construction) concatenate raw. Ratify Option 2 from #1298: enforce the guard at bundle registration time in `EntityTypeManager::addBundleFields()` AND keep a single naming helper (static method on `SqlSchemaHandler` or a small `BundleSubtable` value type) that the other sites call. Both belt and suspenders, because the structural guard prevents the bad input and the helper centralises the format.

### K2 — Read routing must match write routing for `FieldStorage::Data`

Write path (`SqlEntityStorage::splitForStorage()` via `getDataStoredCoreFieldNames()` at line 493) is the source of truth for which fields land in `_data` vs columns. Read path (`SqlEntityQuery::routeFields()`/`resolveField()` at lines 175/135) MUST consult the same registry hint. Ratify: registry hint wins over `SchemaInterface::fieldExists()` on the query side too. No silent dual-source.

### K3 — `_data` JSON value comparisons coerce by declared field type

`SqlEntityQuery::condition()` must inspect the field's declared type (FieldDefinition cast) and either bind the bound parameter accordingly or wrap `json_extract(...)` in `CAST(... AS TEXT)` to make string-vs-integer comparisons commute. The spec for casting/hydration (`docs/specs/entity-system.md` §Casting & hydration architecture, ST-9, #1181) already names FieldDefinition casts as the source of truth at the read boundary. This convention extends that authority into the query-builder boundary.

### K4 — Diagnostic-loop tightening, not throwing

`SqlEntityStorage::mergeBundleSubtableRow()` (#1299) and `EntityTypeManager::addBundleFields()` (#1313 part A) emit `LoggerInterface::warning()`/`notice()` once per `(entity_type, bundle)`, memoized on `bundleSubtableCache` or equivalent. Do not throw. The "open by default, diagnostic-driven" model from `docs/specs/operator-diagnostics.md` is preserved.

### K5 — Diagnostic enumeration is dialect-portable

`HealthChecker::findOrphanSubtables()` enumerates tables via DBAL `AbstractSchemaManager::listTableNames()` filtered against `{base}__%`, with SQLite's `sqlite_master` retained as a fast-path. Test matrix must include at least one non-SQLite run gated behind a docker-compose env var (acceptance lifted verbatim from #1301).

### K6 — `HealthChecker` layer placement (decision required)

`HealthChecker` sits in L0 and imports from L1. Three options on the table (#1300):
- **(a)** Relocate to a higher layer (diagnostic kernel / interface layer). Cleanest. Touches `ConsoleKernel` wiring.
- **(b)** Define an L0 `SchemaDescribable` interface that `packages/entity` and `packages/entity-storage` implement; have `HealthChecker` consume that. Inverts the dependency.
- **(c)** Codify the kernel-adjacent exemption — `HealthChecker` is wired only from `ConsoleKernel`, so it is functionally a bootstrapper. Cheapest. Requires explicit allowlist entry in `bin/check-package-layers`.

The spec must pick one. Mission planner's lean: **(c)** if and only if `bin/check-package-layers` already supports an exemption surface for kernels (which the architectural-remediation mission's S1 work was building toward); otherwise **(a)**. **(b)** invents an interface for one consumer and is over-engineered.

### K7 — Duplicate-registration error names both registrants (#1313 part B)

`EntityTypeRegistrationCollisionException::duplicate(...)` (already extant at `EntityTypeManager.php:108`) must include the FQCN of both the existing and incoming registrant in its message. This is a string-content convention, not a contract change.

---

## PROPOSED CONTRACT — needs ratification (the one architectural exception)

### C1 — Tenancy opt-in declared on `EntityType`, not on the entity class (#1304)

Today: tenancy is opt-in via `HasCommunityInterface` marker on the entity class, checked by service-provider wiring through `is_a()`. Live source: `packages/entity/src/Community/HasCommunityInterface.php`, `HasCommunityTrait.php`, `packages/entity-storage/src/Tenancy/CommunityScope.php`.

Problem: framework-shipped `final` entity classes (e.g., `Waaseyaa\Groups\Group`) cannot be marked by consumers. The class-hierarchy coupling that bundle-scoped fields exist to avoid is exactly what the marker reintroduces.

Two shapes proposed in the issue body:

1. **Declarative key on `EntityType`** — e.g., `tenancy: ['scope' => 'community']` on construction. Lives next to entity-keys/bundle-entity-type. Cheapest wiring change. New named constructor parameter on `EntityType`. **Mission planner's lean.**
2. **Separate `TenantScope` registration hook** — `$container->tag('entity.tenant_scope', entityType: 'group', scope: CommunityScope::class)`. Decouples tenancy from core entity-type declaration. Heavier, but extensible (future `RegionScope`, `OrgScope` without re-touching `EntityType`).

Either path requires:
- A deprecation cycle for `HasCommunityInterface` (log once on first wiring per entity-type id; remove in next minor).
- `CommunityScope::isActive()` keeps the per-request runtime gate; only the **opt-in check** moves.
- `SqlStorageDriver` wiring reads the new declaration instead of `is_a()`.
- Migration note in spec for Minoo and any external consumer.

This is the only proposed addition to a public contract surface in the entire mission. It requires explicit ratification before WP05 (per roster below) enters the implement lane.

---

## Decision: NO-SPLIT

Eight issues, one storage subsystem, one set of files. Cleavage analysis:

| Cluster | Issues | Files touched |
|---|---|---|
| Bundle naming + DX | 1298, 1313 | `SqlSchemaHandler`, `SqlEntityStorage`, `SqlEntityQuery`, `EntityTypeManager` |
| Read/write symmetry | 1257, 1308 | `SqlEntityQuery`, `SqlEntityStorage`, `FieldDefinition` (read-only) |
| Bundle load diagnostics | 1299 | `SqlEntityStorage` |
| Health-check portability | 1300, 1301 | `HealthChecker`, `bin/check-package-layers` (exemption) |
| Tenancy opt-in | 1304 | `EntityType`, `CommunityScope`, service-provider wiring |

Every cluster touches `SqlEntityStorage` or `SqlEntityQuery` (or both) **except** 1300/1301 (HealthChecker only). Bundle naming (1298) and FieldStorage routing (1308) both rewrite `SqlEntityQuery::resolveField()`. Splitting into two missions would force one to merge and the other to re-derive against a moved `resolveField()`. Cluster-collapse is the right call.

The HealthChecker pair (1300/1301) could in theory ship as a standalone mission (no overlap with `SqlEntityQuery`/`SqlEntityStorage` files), but the diagnostic codes they emit (`MISSING_BUNDLE_SUBTABLE`, `ORPHAN_BUNDLE_SUBTABLE`) are about the same invariants the storage-side hardening enforces. Conceptual coupling. Keep them together.

The tenancy contract (1304) is structurally independent at the file level — but K6 (HealthChecker layer) and C1 (tenancy on EntityType) both require deciding "what belongs in `EntityType` declaration vs in service-provider wiring." Decoupled WPs in the same mission with one shared spec section.

NO-SPLIT.

---

## Proposed WP roster

Sequencing puts contract-touching work last so the conventions can settle in spec.md before they ship.

| WP | Title | Outcome | Issues | Depends on |
|---|---|---|---|---|
| WP02 | Spec/contract ratification | spec.md and tasks.md expanded with K1-K7 + C1; `docs/specs/entity-system.md` and `docs/specs/bundle-scoped-storage.md` updated; HealthChecker layer placement decided (K6); tenancy mechanism shape decided (C1: option 1 vs 2) | All | — |
| WP03 | Bundle naming centralization | Single helper for `{base}__{bundle}`; structural guard at `EntityTypeManager::addBundleFields()`; raw concat removed at SqlEntityStorage:672, SqlEntityStorage:201 (call-site), SqlEntityQuery:139, SqlEntityQuery:370 | #1298 | WP02 (K1) |
| WP04 | Read/write symmetry for `FieldStorage::Data` | `SqlEntityQuery::routeFields()` consults registry hint; tests for the asymmetric case (column lingers after hint added) | #1308 | WP02 (K2) |
| WP05 | `_data` value coercion in query builder | `SqlEntityQuery::condition()` casts numeric strings (or wraps json_extract in CAST AS TEXT) per declared field type; reproduction from #1257 becomes a passing test | #1257 | WP02 (K3), WP04 |
| WP06 | Bundle-load drift logging | Log once per `(entity_type, bundle)` in `mergeBundleSubtableRow()`/`mergeBundleSubtableRowsBatch()` when subtable missing; memoize on existing cache | #1299 | WP02 (K4) |
| WP07 | Duplicate-registration DX + shadow-collision notice | (A) `addBundleFields()` notice when subtable absent; (B) `EntityTypeRegistrationCollisionException::duplicate` message names both registrants | #1313 | WP02 (K4, K7) |
| WP08 | HealthChecker layer placement | Apply chosen option (a/b/c) from K6; if option (c), add explicit `bin/check-package-layers` allowlist entry; if (a), move file and rewire `ConsoleKernel`; if (b), introduce `SchemaDescribable` interface in foundation | #1300 | WP02 (K6) |
| WP09 | Portable orphan detection | DBAL `AbstractSchemaManager::listTableNames()` path; SQLite fast-path retained; test matrix gates a non-SQLite run behind env var | #1301 | WP02 (K5), WP08 (file may have moved) |
| WP10 | Tenancy opt-in via EntityType | Implement chosen mechanism from C1; deprecate `HasCommunityInterface` (log-once per entity-type-id); update `CommunityScope` opt-in check; update service-provider wiring; update `docs/specs/entity-system.md` and groups package | #1304 | WP02 (C1 ratified) |
| WP11 | Kernel-path integration test | Single end-to-end test that exercises: register entity type → register bundle fields → save → query (with `_data` value) → load → health-check. Locks all hardened invariants in one place. The charter explicitly names this as a deliverable. | All | WP03-WP10 |

WP01 was the decomposition (this file).

---

## Drift flags

1. **Issues are OPEN, not closed.** Mission spec says "Each closed issue carries a cross-link comment pointing back to this mission." All eight (`1257`, `1298`, `1299`, `1300`, `1301`, `1304`, `1308`, `1313`) are still OPEN per `gh issue view`. Per the kill-list methodology in `docs/triage/2026-04-30-pass-1-kill-list.md`, they were marked KEEP-MISSION (entity-storage-hardening) but **never closed**. Either the spec language is aspirational or the cross-link/close pass is incomplete. WP02 should either: (a) close them now with cross-link comments, or (b) update the spec language to "tracked open under this mission until WP merge."

2. **#1313 part B may already be implemented.** Issue body proposes the duplicate-registration message name both registrants. Live source has `EntityTypeRegistrationCollisionException::duplicate(...)` already wired at `EntityTypeManager.php:108`. Verify the message content before WP07 to avoid a no-op WP. (`grep -A 12` returned empty, suggesting the static factory body wasn't found in that file path — investigate).

3. **#1304 names a `Waaseyaa\Groups\Group` final class** as a blocker. Verify that the `groups` package ships a `final` entity class today and that Minoo's `App\Entity\Group implements HasCommunityInterface` is the canonical adopter. The mission's deprecation surface depends on it.

4. **#1300 cites `bin/check-package-layers` as enforcer** of the layer rule. If that script does not currently flag `foundation/src/Diagnostic/HealthChecker.php`'s L1 imports, then the violation is real but unenforced — the script (or the mission acceptance) must close that gap, or option (c) (codified exemption) becomes the only honest stance. Architectural-remediation mission's S1 was building toward exemption support; check its merge state before WP02.

5. **`docs/specs/bundle-scoped-storage.md` exists** (good — confirmed via batch). Three of the eight issues cite it. WP02 must update it to reflect K1, K4 explicitly.

6. **`database-legacy` namespace.** Issue bodies don't drift on this, but for completeness: `packages/database-legacy/composer.json` autoloads `Waaseyaa\Database\\` (not `Waaseyaa\DatabaseLegacy\\`). Per ADR-007 and `CLAUDE.md`, any new code in this mission referencing the legacy package uses `Waaseyaa\Database\*`.

7. **Anchor #1257's "Workaround (applied in Minoo)"** says callers cast to `(int)` at the call site. Verify that the Minoo workaround can be removed once WP05 lands, and add that to WP05's verification evidence.

8. **No `tenancy` or `scope` keys exist on `EntityType` today.** Confirmed via grep. C1 is genuinely additive. No current consumer uses these names accidentally.

---

## Risks

1. **K2 (read/write symmetry) plus K3 (`_data` coercion) plus K1 (naming) all converge on `SqlEntityQuery::resolveField()`.** Three WPs touch the same hot method. Sequence is WP03 → WP04 → WP05 to keep churn linear, but rebase pressure between them is real. Land each into main before the next opens its branch.

2. **The kernel-path integration test (WP11)** is the charter's explicit "lock the invariants" deliverable. If WPs 03-10 ship without WP11, the mission's stated purpose is unmet even if every individual fix is correct. WP11 is non-negotiable for mission acceptance.

3. **C1's deprecation cycle** for `HasCommunityInterface` will produce log noise on every consumer's first request. Document the deprecation log cadence in spec.md and provide a migration recipe in the groups package's CHANGELOG.

4. **#1301's non-SQLite test matrix** introduces a docker-compose dependency on the CI side. If the project's CI does not currently run docker-compose, this is a new infrastructure surface. WP09 acceptance should be gated on CI capability — if the env var is unsupported, WP09 ships only the code path with a documentation note in `docs/specs/operator-diagnostics.md` that orphan detection on MySQL/Postgres is implemented but not yet CI-verified.

5. **Layer-violation cluster (K6 / WP08)** may surface that other L0 packages also import L1 — `HealthChecker` is the named offender, but if `bin/check-package-layers` doesn't currently fire on it, there may be more. Scope creep risk. WP08 explicitly handles only `HealthChecker`; any sibling violations get filed as new issues, not absorbed.

6. **Anchor stays open** even after merge per user instruction. WP11 acceptance includes "anchor #1257 issue body updated with link to merged commit and verified resolution," not "anchor closed."

---

## Acceptance for the mission as a whole

- All eight issue bodies map to merged WPs (or closed-as-superseded with cross-link to the merging WP).
- `docs/specs/entity-system.md` and `docs/specs/bundle-scoped-storage.md` updated to bless K1-K7 + C1.
- `bin/check-package-layers` runs clean on `packages/foundation/` (either by relocation, inversion, or codified exemption).
- WP11's kernel-path integration test passes and exercises every hardened invariant (`_data` int comparison, `FieldStorage::Data` symmetry, bundle naming guard, bundle-load logging, health-check codes, tenancy via EntityType).
- Anchor #1257 stays open per user flag; body annotated with merged-commit references for traceability.
- No new `final` class added to entity-storage that a consumer would need to extend.
- No new dependency from L0 to L1+.

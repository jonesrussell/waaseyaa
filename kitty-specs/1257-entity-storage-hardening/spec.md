# Mission spec: 1257-entity-storage-hardening

**Charter:** Lock the entity-storage invariant pipeline. Tighten seams across `SqlEntityStorage`, `SqlEntityQuery`, `SqlSchemaHandler`, `EntityTypeManager`, and `HealthChecker`; restore read/write symmetry for `FieldStorage::Data`; coerce `_data` JSON value comparisons by declared field type; portable orphan detection across SQLite/MySQL/PostgreSQL; close the layer-discipline gap on `HealthChecker`; move tenancy opt-in off the marker interface and onto `EntityType`. Cap with a kernel-path integration test that exercises every hardened invariant in one place.

**Milestone:** Track 1 — Entity system & hydration

**Origin:** Pass 1 architect-mode triage (2026-04-30). Mission anchors on `#1257` (open, user-flagged keep) and tracks 7 sibling issues marked KEEP-MISSION in the kill list.

**Decomposition artifact:** `decomposition.md` in this directory.

---

## Decision: NO-SPLIT (10 WPs)

Eight issues, one storage subsystem, three converging hot files (`SqlEntityStorage`, `SqlEntityQuery`, `SqlSchemaHandler`). Splitting would force one half-mission to merge and the other to re-derive against a moved `resolveField()`. HealthChecker pair (1300/1301) is structurally separable but emits diagnostic codes about the same invariants the storage-side hardening enforces — keep coupled. Tenancy contract (1304) is the architectural cleavage but shares a "what belongs in `EntityType` vs in service-provider wiring?" question with K6 (HealthChecker layer placement); decoupled WPs in the same mission with one shared spec section.

**Mode: mechanical-with-one-architectural-asterisk.** Follows the `1335` pattern for K1-K7 hardening conventions; one architectural contract (C1) carved off into its own WP.

| WP | Title | Surface | Issues |
|----|-------|---------|--------|
| WP02 | spec/contract ratification | spec.md, tasks.md, `docs/specs/entity-system.md`, `docs/specs/bundle-scoped-storage.md` | All |
| WP03 | bundle-naming centralization | one helper + registration-time guard | #1298 |
| WP04 | read/write symmetry for `FieldStorage::Data` | `SqlEntityQuery::routeFields()` consults registry | #1308 |
| WP05 | `_data` value coercion in query builder | `SqlEntityQuery::condition()` casts per declared type | #1257 |
| WP06 | bundle-load drift logging | once-per-bundle log in `mergeBundleSubtableRow*` | #1299 |
| WP07 | duplicate-registration DX + shadow-collision notice | `addBundleFields()` notice; collision exception names both | #1313 |
| WP08 | HealthChecker layer placement | apply chosen K6 option (a/b/c) | #1300 |
| WP09 | portable orphan detection | DBAL `listTableNames()`; SQLite fast-path retained | #1301 |
| WP10 | tenancy opt-in via EntityType | implement chosen C1 shape; deprecate `HasCommunityInterface` | #1304 |
| WP11 | kernel-path integration test | end-to-end lock for every hardened invariant | All |

**Sequencing.** WP02 first (ratifies K1-K7 + C1). WP03 → WP04 → WP05 sequenced because all three rewrite `SqlEntityQuery::resolveField()` (linear churn beats parallel rebases). WP06, WP07, WP08, WP09 may run in parallel after WP02. WP09 nominally depends on WP08 (file may have moved). WP10 in parallel with the rest after WP02. **WP11 is the lock — last, depends on all others, non-negotiable for mission acceptance.**

Per-WP detail in `tasks.md`.

---

## Open-issue handling — RATIFIED Path X (2026-04-30)

Mission scaffold spec language originally read *"Each closed issue carries a cross-link comment pointing back to this mission"* — but live state showed all 8 issues OPEN. Pass-1 kill list moved them logically (KEEP-MISSION) but did not execute the close-and-link pass.

**Decision: Path X.** WP02 closes `#1298`, `#1299`, `#1300`, `#1301`, `#1304`, `#1308`, `#1313` with cross-link comments pointing to this mission. **Anchor `#1257` stays open per user flag** — its body gets annotated with merged-commit references at WP11 acceptance, not closed. Matches the 824/619 missions' pattern for sibling absorption while honoring the anchor's keep-flag.

---

## Ratified conventions (K1-K7) — approved 2026-04-30

These are the conventions the mission codifies. They are not new public types. They are commitments about how the existing pipeline must behave going forward. K1-K5 and K7 batch-ratified as accepted conventions. K6 individually ratified as **option (c) with explicit kernel-adjacent exemption** — see below.

### K1 — Bundle subtable naming is a single helper

Rule: `{base}__{bundle}` and the `__`-in-bundle-id structural guard belong to one shared helper (static method on `SqlSchemaHandler` or a small `BundleSubtable` value type) consumed by `SqlEntityStorage`, `SqlEntityQuery`, and `SqlSchemaHandler`. Plus enforce the guard at bundle-registration time in `EntityTypeManager::addBundleFields()`. Belt and suspenders.

### K2 — Read routing must match write routing for `FieldStorage::Data`

Write path (`SqlEntityStorage::splitForStorage()` via `getDataStoredCoreFieldNames()`) is the source of truth for which fields land in `_data` vs columns. Read path (`SqlEntityQuery::routeFields()` / `resolveField()`) MUST consult the same registry hint. Registry hint wins over `SchemaInterface::fieldExists()` on the query side too. No silent dual-source.

### K3 — `_data` JSON value comparisons coerce by declared field type

`SqlEntityQuery::condition()` inspects FieldDefinition cast and either binds the parameter accordingly or wraps `json_extract(...)` in `CAST(... AS TEXT)` to make string-vs-integer comparisons commute. Extends the casting-and-hydration source-of-truth (`docs/specs/entity-system.md` §Casting & hydration, ST-9, #1181) into the query-builder boundary.

### K4 — Diagnostic-loop tightening, not throwing

`SqlEntityStorage::mergeBundleSubtableRow*()` (#1299) and `EntityTypeManager::addBundleFields()` (#1313 part A) emit `LoggerInterface::warning()` / `notice()` once per `(entity_type, bundle)`, memoized on `bundleSubtableCache` or equivalent. **Do not throw.** Preserves the open-by-default, diagnostic-driven model from `docs/specs/operator-diagnostics.md`.

### K5 — Diagnostic enumeration is dialect-portable

`HealthChecker::findOrphanSubtables()` enumerates tables via DBAL `AbstractSchemaManager::listTableNames()` filtered against `{base}__%`, with SQLite's `sqlite_master` retained as a fast-path. Test matrix includes at least one non-SQLite run gated behind a docker-compose env var. (Acceptance lifted verbatim from #1301.)

### K6 — HealthChecker layer placement — RATIFIED option (c)

`HealthChecker` sits in L0 and imports `Waaseyaa\Entity\*` + `Waaseyaa\EntityStorage\SqlSchemaHandler` + `Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface` (all L1).

**Decision: option (c) — codified kernel-adjacent exemption.** `HealthChecker` is wired only from `ConsoleKernel`, so it is functionally a bootstrapper (sibling category to the kernel-bootstrapper exemption already documented in `CLAUDE.md` Layer Architecture). WP02 verifies that `824-architectural-remediation` mission's S1 work has merged the exemption surface in `bin/check-package-layers`. WP08 then adds an explicit allowlist entry naming `packages/foundation/src/Diagnostic/HealthChecker.php` with rationale comment.

**Hard prerequisite (verified in WP02):** if 824 S1 has NOT merged the exemption surface, WP08 cannot ship as option (c). In that case the mission is blocked on 824 — surface to user, do not silently fall back to option (a). Honest failure beats silent rebase.

### K7 — Duplicate-registration error names both registrants

`EntityTypeRegistrationCollisionException::duplicate(...)` (already extant at `EntityTypeManager.php:108`) message must include the FQCN of both existing and incoming registrant. String-content convention, not a contract change. (Drift flag D2: verify the message body before WP07 to avoid a no-op WP — initial grep was inconclusive.)

---

## Ratified contract (C1) — approved 2026-04-30 (Option 1)

### C1 — Tenancy opt-in on `EntityType`, declarative key (#1304)

Today: `HasCommunityInterface` marker on the entity class, `is_a()` check in service-provider wiring. **Problem:** framework-shipped `final` entity classes (e.g., `Waaseyaa\Groups\Group`) cannot be marked by consumers — exactly the class-hierarchy coupling that bundle-scoped fields exist to avoid.

**Decision: Option 1 — declarative key on `EntityType`.** WP10 adds a named constructor parameter to `EntityType`: `tenancy: ['scope' => 'community']` (or null when not scoped). Lives next to `entity-keys` / `bundle-entity-type`. Cheapest wiring change. Option 2 (separate `TenantScope` registration hook) explicitly rejected — the extensibility argument for future `RegionScope` / `OrgScope` is hypothetical and adds a registration surface for one current consumer.

Required changes (locked at ratification):

- Deprecation cycle for `HasCommunityInterface`: log once on first wiring per entity-type id via `LoggerInterface::warning()`. Removal scheduled for the next minor release. Migration recipe in `groups` package CHANGELOG.
- `CommunityScope::isActive()` keeps the per-request runtime gate; only the **opt-in check** moves to consult `EntityType::getTenancy()`.
- `SqlStorageDriver` wiring reads `$entityType->getTenancy()` instead of `is_a($entityType->getClass(), HasCommunityInterface::class, true)`.
- Migration note authored in `docs/specs/entity-system.md` and surfaced to Minoo team before WP10 merges.
- Acknowledgment recorded: this is a **breaking change** with deprecation cycle, not a "lock invariants" tweak. Approved under the modern stance (PHP 8.4+, no legacy, breaking changes welcome).

C1 is the only public-contract addition in the mission. WP10 implements; WP11 verifies via the kernel-path integration test.

---

## Drift flags

| # | Flag | Resolution |
|---|------|------------|
| D1 | All 8 issues are OPEN; spec language claims they're closed | RESOLVED Path X: WP02 closes 7 siblings with cross-link comments; anchor #1257 stays open per user flag |
| D2 | #1313 part B may already be implemented at `EntityTypeManager.php:108` | WP07 verifies message body before doing work; if already correct, WP07 ships only #1313 part A |
| D3 | #1304 names `Waaseyaa\Groups\Group` final class as blocker | WP02 confirms `groups` package ships a `final` entity and Minoo's `App\Entity\Group` is the canonical adopter |
| D4 | `bin/check-package-layers` may not currently fire on HealthChecker | WP02 verifies 824 mission S1 merge state; K6 option (c) only viable if exemption surface exists |
| D5 | `docs/specs/bundle-scoped-storage.md` exists | WP02 updates it to bless K1, K4 explicitly |
| D6 | `database-legacy` namespace is `Waaseyaa\Database` | Any new code uses `Waaseyaa\Database\*` per ADR-007 / `CLAUDE.md` |
| D7 | Anchor #1257's Minoo workaround `(int)` cast | WP05 verification evidence: workaround removable post-merge |
| D8 | No `tenancy` / `scope` keys on `EntityType` today | C1 is genuinely additive; no accidental collision |

---

## Acceptance

The mission accepts when ALL of:

1. All 8 issues map to merged WPs (or per Path X / Path Y resolution from "Open-issue handling").
2. `docs/specs/entity-system.md` and `docs/specs/bundle-scoped-storage.md` updated to bless K1-K7 + C1.
3. `bin/check-package-layers` runs clean on `packages/foundation/` (via relocation, inversion, or codified exemption per K6 ratified option).
4. WP11's kernel-path integration test passes and exercises every hardened invariant: `_data` int comparison, `FieldStorage::Data` symmetry, bundle naming guard, bundle-load logging, health-check codes (`MISSING_BUNDLE_SUBTABLE`, `ORPHAN_BUNDLE_SUBTABLE`), tenancy via EntityType.
5. Anchor `#1257` stays open per user flag; body annotated with merged-commit references for traceability.
6. No new `final` class added to entity-storage that a consumer would need to extend.
7. No new dependency from L0 to L1+ (post-K6 resolution).

---

## Risks

1. **K1 + K2 + K3 all rewrite `SqlEntityQuery::resolveField()`.** Three WPs converging on one hot method. Sequencing WP03 → WP04 → WP05 keeps churn linear; rebase pressure between them is real. Land each into main before the next opens its branch.
2. **WP11 is the charter's stated lock.** If WPs 03-10 ship without WP11, the mission's purpose is unmet even if every individual fix is correct. **Non-negotiable for mission acceptance.**
3. **C1 deprecation cycle** for `HasCommunityInterface` produces log noise on every consumer's first request per entity-type id. Document cadence in spec; provide migration recipe in groups package CHANGELOG.
4. **#1301 non-SQLite test matrix** introduces a docker-compose dependency on CI. If unsupported, WP09 ships code path with a doc note in `docs/specs/operator-diagnostics.md` that orphan detection on MySQL/Postgres is implemented but not yet CI-verified.
5. **K6 / WP08 may surface sibling layer violations.** `HealthChecker` is the named offender, but if `bin/check-package-layers` doesn't currently fire on it, more L0→L1 imports may exist. WP08 explicitly handles only `HealthChecker`; siblings get filed as new issues, not absorbed.
6. **Anchor stays open** per user instruction. WP11 acceptance includes "anchor #1257 issue body updated with link to merged commit and verified resolution," not "anchor closed."

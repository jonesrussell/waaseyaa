# Tasks / work packages — 1257-entity-storage-hardening

WP01 was the Pass-2 decomposition (`decomposition.md` in this directory). WP02 ratifies K1-K7 + C1, picks the K6 HealthChecker option, picks the C1 tenancy shape, decides Path X vs Path Y for the open-issue handling, and updates `docs/specs/entity-system.md` + `docs/specs/bundle-scoped-storage.md`. Implementation WPs follow.

| WP | Title | Outcome | Status |
|----|-------|---------|--------|
| WP01 | mission-decomposition | `decomposition.md`. NO-SPLIT decision. K1-K7 conventions + C1 contract surfaced. 8 drift flags raised. Mode = mechanical-with-one-architectural-asterisk. | done |
| WP02 | spec-execution-and-doc-update | K1-K7 + C1 already ratified in `spec.md` (2026-04-30). WP02 executes: (1) verify 824 S1 exemption-surface has merged into `bin/check-package-layers` — hard prerequisite for K6 option (c), surface to user if blocked; (2) close `#1298`, `#1299`, `#1300`, `#1301`, `#1304`, `#1308`, `#1313` with cross-link comments per Path X; (3) update `docs/specs/entity-system.md` and `docs/specs/bundle-scoped-storage.md` to bless K1-K7 + C1; (4) verify D2 (`EntityTypeRegistrationCollisionException::duplicate` body) and D3 (`Waaseyaa\Groups\Group` final + Minoo adopter); (5) author migration note for `HasCommunityInterface` deprecation. | unscheduled |
| WP03 | bundle-naming-centralization | Single helper for `{base}__{bundle}`; structural guard at `EntityTypeManager::addBundleFields()`; raw concat removed at `SqlEntityStorage:672`, `SqlEntityStorage:201` (call-site), `SqlEntityQuery:139`, `SqlEntityQuery:370`. Tests for the guard and helper. | unscheduled |
| WP04 | read-write-symmetry-fieldstorage-data | `SqlEntityQuery::routeFields()` consults registry hint; tests cover the asymmetric case (legacy column lingers after a field gains the storage hint). | unscheduled |
| WP05 | data-value-coercion-in-query-builder | `SqlEntityQuery::condition()` casts numeric strings (or wraps `json_extract` in `CAST AS TEXT`) per declared field type. Reproduction from `#1257` becomes a passing regression test. Minoo `(int)` workaround verified removable. | unscheduled |
| WP06 | bundle-load-drift-logging | Log once per `(entity_type, bundle)` in `mergeBundleSubtableRow()` / `mergeBundleSubtableRowsBatch()` when subtable missing; memoize on existing cache. No throw. | unscheduled |
| WP07 | duplicate-registration-dx-and-shadow-collision-notice | (A) `addBundleFields()` notice when subtable absent. (B) `EntityTypeRegistrationCollisionException::duplicate` message names both registrants. Verify D2: if (B) is already correct, ship only (A). | unscheduled |
| WP08 | healthchecker-layer-placement | Apply chosen K6 option (a/b/c). If (c), add explicit `bin/check-package-layers` allowlist entry. If (a), move file and rewire `ConsoleKernel`. If (b), introduce `SchemaDescribable` interface in foundation. | unscheduled |
| WP09 | portable-orphan-detection | DBAL `AbstractSchemaManager::listTableNames()` path; SQLite fast-path retained; test matrix gates a non-SQLite run behind env var. If CI does not support docker-compose, ship code with doc note in `docs/specs/operator-diagnostics.md`. | unscheduled |
| WP10 | tenancy-opt-in-via-entitytype | Implement chosen C1 mechanism. Deprecate `HasCommunityInterface` (log-once per entity-type id). Update `CommunityScope` opt-in check. Update `SqlStorageDriver` wiring. Update `docs/specs/entity-system.md` and `groups` package. Migration recipe in CHANGELOG. | unscheduled |
| WP11 | kernel-path-integration-test | Single end-to-end test: register entity type → register bundle fields → save → query (with `_data` value) → load → health-check. Locks all hardened invariants in one place. **Charter's stated lock; non-negotiable for mission acceptance.** | unscheduled |

**Review gate:** Each WP runs through Spec Kitty implement → review per `docs/specs/workflow.md`. WP02 must reach `approved` before any other WP can enter `implement`.

## Dependencies (between WPs)

- WP02 → WP03, WP04, WP05, WP06, WP07, WP08, WP09, WP10 (ratification gate; nothing implements until conventions and contract are locked)
- WP03 → WP04 → WP05 (linear sequencing on `SqlEntityQuery::resolveField()` to keep churn linear)
- WP06, WP07 ⟂ WP03/WP04/WP05 (parallel; touch different files)
- WP09 → WP08 (file may have moved if K6 option (a) chosen)
- WP08, WP10 ⟂ rest (parallel after WP02)
- WP11 → WP03 + WP04 + WP05 + WP06 + WP07 + WP08 + WP09 + WP10 (lock; depends on all)

## Per-WP gating notes

- **WP02** is execution, not ratification — all decisions already locked in `spec.md` (2026-04-30). WP02 must verify the 824 S1 exemption-surface prerequisite for K6 option (c) before any implementation WP runs; if 824 S1 has not merged the surface, mission is blocked on 824 — surface to user, do not silently fall back.
- **WP03 / WP04 / WP05** all touch `SqlEntityQuery::resolveField()`. Open each branch only after the prior one merges into main. Do not run them in parallel even though they're separate WPs.
- **WP07 part B** must verify `EntityTypeRegistrationCollisionException::duplicate` body before doing work. If the message already names both registrants, WP07 ships only part A.
- **WP08** option choice may surface that other L0 packages also import L1. Out of scope for this mission; file new issues for siblings, do not absorb.
- **WP09** acceptance is gated on CI capability. If docker-compose isn't supported, ship the code path and document the gap in `docs/specs/operator-diagnostics.md`.
- **WP10** deprecation cycle for `HasCommunityInterface` produces log noise on every consumer's first request per entity-type id. Document cadence in spec; provide migration recipe in `groups` CHANGELOG. Surface to Minoo team before merge.
- **WP11** is the charter lock. The mission does not accept without it, even if WPs 03-10 all ship correctly.

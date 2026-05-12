# Plan: 1257-entity-storage-hardening

Five phases, mapped to WP02-WP11. Phase boundaries are merge points; nothing crosses a phase line until the prior phase's WPs are `approved` per `docs/specs/workflow.md`.

## Phase 1 — Spec and contract ratification (WP02)

Objective: lock all conventions and contracts before any code WP starts. Decisions captured in writing, signed off by user.

- User ratifies K1-K7. K6 option (a/b/c) chosen explicitly.
- User ratifies C1. Shape (Option 1: declarative `tenancy` key on `EntityType` / Option 2: separate `TenantScope` registration hook) chosen explicitly.
- User picks Path X (close-and-link 7 sibling issues now, leave anchor open) vs Path Y (keep all 8 open until per-WP merge). Spec language updated to match.
- WP02 verifies `824-architectural-remediation` mission's S1 work has merged exemption-surface support into `bin/check-package-layers`. If yes, K6 option (c) viable. If no, K6 falls back to (a).
- WP02 verifies `EntityTypeRegistrationCollisionException::duplicate` message body (drift D2). Result feeds WP07 scope.
- WP02 confirms `Waaseyaa\Groups\Group` ships `final` and Minoo's `App\Entity\Group` is the canonical adopter (drift D3). Result feeds WP10 deprecation surface.
- `docs/specs/entity-system.md` and `docs/specs/bundle-scoped-storage.md` updated to bless K1-K7 + C1.

Exit criteria: WP02 review approved. No code changes outside `docs/specs/`.

## Phase 2 — Linear hot-method hardening (WP03 → WP04 → WP05)

Objective: rewrite `SqlEntityQuery::resolveField()` and adjacent methods in three sequential passes. Land each into main before the next opens its branch.

- WP03: bundle naming centralization. One helper. Structural guard at registration time. Raw-concat removed from four sites.
- WP04: read/write symmetry for `FieldStorage::Data`. Registry hint wins on the read side too.
- WP05: `_data` JSON value comparison coerces by declared field type. Anchor #1257's reproduction case becomes a passing regression test.

Exit criteria: each WP merges before the next branches. `SqlEntityQuery::resolveField()` is internally consistent across naming, routing, and value coercion.

## Phase 3 — Diagnostics and DX hardening (WP06, WP07, WP08, WP09 — parallel)

Objective: tighten the diagnostic loop and close the layer gap on `HealthChecker`. These four WPs touch different files and may ship in parallel after Phase 2.

- WP06: bundle-load drift logging once per `(entity_type, bundle)`. No throw.
- WP07: duplicate-registration error names both registrants; bundle-fields registration emits notice when subtable absent. (Verify D2 first.)
- WP08: HealthChecker layer placement per ratified K6 option.
- WP09: portable orphan detection; non-SQLite test matrix gated on CI capability. Nominally depends on WP08 (file may have moved).

Exit criteria: all four merged. `bin/check-package-layers` runs clean on `packages/foundation/`. Health checks portable across SQLite/MySQL/PostgreSQL.

## Phase 4 — Tenancy contract migration (WP10)

Objective: move tenancy opt-in off the marker interface and onto the ratified C1 mechanism. May run in parallel with Phase 3.

- Implement chosen C1 shape on `EntityType`.
- `SqlStorageDriver` wiring reads the new declaration.
- `HasCommunityInterface` deprecated with log-once-per-entity-type cadence. Removal scheduled for next minor.
- Migration recipe in `groups` package CHANGELOG. Minoo team notified before merge.
- `docs/specs/entity-system.md` updated.

Exit criteria: WP10 merged. Bimaaji and Minoo can opt in without subclassing.

## Phase 5 — The lock (WP11)

Objective: prove every hardened invariant works together in a kernel-path integration test. **This phase is the charter's stated deliverable.** Without it, the mission does not accept even if every Phase 2-4 WP shipped correctly.

- One end-to-end test exercises:
  - register entity type with `tenancy` key (or `TenantScope` hook per C1)
  - register bundle fields (subtable created; name guard fires on bad input)
  - save entity (write path uses registry hint; `_data` blob coerced)
  - query (`_data` value comparison commutes; routing matches write)
  - load (bundle subtable joined; missing-subtable warning fires on synthetic drift)
  - health-check (orphan detection portable; missing-subtable code emits)
- Anchor `#1257` body annotated with merged-commit references.

Exit criteria: WP11 approved. Mission accepts.

## Cross-phase invariants

- No new dependency from L0 to L1+. K6 resolution is the load-bearing test of this.
- No new `final` class added to entity-storage that consumers would need to extend. (The whole point of C1.)
- `composer verify` (root command from 824 mission) gates every WP merge.
- No `psr/log`. Use `Waaseyaa\Foundation\Log\LoggerInterface` everywhere.
- Anchor `#1257` stays open per user flag through the entire mission. Closure decisions for the other 7 issues follow Path X or Path Y per WP02 ratification.

## Sequencing summary

```
WP02 ─┬─→ WP03 ─→ WP04 ─→ WP05 ─┐
       │                          │
       ├─→ WP06 ─────────────────┤
       │                          │
       ├─→ WP07 ─────────────────┤
       │                          ├─→ WP11 (lock)
       ├─→ WP08 ─→ WP09 ─────────┤
       │                          │
       └─→ WP10 ─────────────────┘
```

WP03→WP04→WP05 is the critical path. WP06, WP07, WP10 are parallel after WP02. WP08→WP09 is a small dependency chain. WP11 gates everything.

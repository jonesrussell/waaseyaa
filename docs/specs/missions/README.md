# Waaseyaa Mission Manifest

**Generated:** 2026-05-11
**Updated:** 2026-05-13 — **M-006** (`entity-storage-translations-v1`) shipped (squash `0f7e1809a`), clearing one of two M-004 prerequisites; M-001 remains shipped (squash `509e31fb7`).
**Status:** Six missions on the manifest. M-001 and M-006 shipped. M-002/M-003 ready. M-004 still blocked on the ADR 015 listing-pipeline prereq; M-005 still blocked on M-002.

Each subdirectory contains a `mission.json` filing-ready metadata file. The canonical spec for each mission lives at the path given in `mission.json:spec_path`.

## Mission roster

| ID | Title | Spec | Status | Files in `docs/specs/missions/<dir>/` |
|---|---|---|---|---|
| M-001 | Entity Storage v2 | `docs/specs/entity-storage-v2.md` | **shipped 2026-05-11** (squash `509e31fb7`) | `mission.json` |
| M-002 | Migration Platform v1 | `docs/specs/migration-platform-v1.md` | ready | `mission.json` |
| M-003 | Config Management v1 | `docs/specs/config-management-v1.md` | ready (verify validation pipeline) | `mission.json` |
| M-004 | Two-Axis Translation × Revisions | `docs/specs/entity-storage-translatable-revisions.md` | blocked (waits on listing-pipeline-v1; single-axis-translation prereq cleared by M-006 on 2026-05-13) | `mission.json` |
| M-005 | WordPress Source Reader | `docs/specs/waaseyaa-migrate-source-wordpress.md` | blocked (waits on M-002) | `mission.json` |
| M-006 | Entity Storage — Single-Axis Translations v1 | `docs/specs/entity-storage-translations-v1.md` | **shipped 2026-05-13** (squash `0f7e1809a`); satisfies charter §3.2 beta-entry criterion 9 (per-field translation) | `mission.json` |

## Cross-mission dependency graph

```
                          ┌─────────────────────────┐
                          │  Charter ratification   │
                          │  (Russell merges §12.4) │
                          └────────────┬────────────┘
                                       │
                            ┌──────────┴───────────┐
                            ▼                      ▼
                       M-001 starts          M-003 starts (independent)
                       (no prereqs)          (verify validators first)
                            │
            ┌───────────────┼──────────────┐
            │               │              │
            ▼               ▼              ▼
         WP01-WP04      WP04 + WP08    WP05-WP12
            │           shipped first
            │               │
            │               ▼
            │        M-002 WP05 unblocked
            │               │
            │       ┌───────┼────────┐
            │       ▼       ▼        ▼
            │   M-002 WPs 06,07,08,09 (parallel)
            │           │
            │           ▼
            │       M-002 WP11 + WP12 (close)
            │           │
            │           ▼
            │     M-005 starts (WordPress reader)
            │     (separate package, separate repo)
            ▼
         M-001 shipped 2026-05-11
            │
            ▼
   M-006 shipped 2026-05-13 (single-axis translations — BETA-GATE cleared)
            │
            │   ┌── listing-pipeline-v1 (TBD spec, ADR 015) ── remaining M-004 prereq
            │   │
            ▼   ▼
       both prereqs satisfied (translation: done; listing-pipeline: pending)
            │
            ▼
         M-004 starts (waits on listing-pipeline-v1 only)
```

## Filing order (recommended)

1. **M-001** — Entity Storage v2. No prereqs. WP01 starts immediately.
2. **M-003** — Config Management v1. Standalone after verifying `FieldDefinition::validators()` is shipped. Can run fully in parallel with M-001.
3. **M-002** — Migration Platform v1. WPs 01–04, 09, 10 can start in parallel with M-001 / M-003. WP05 waits on M-001 WP04+WP08.
4. **M-005** — WordPress Source Reader. Starts after M-002 acceptance criterion 8 satisfied. Lives in a separate composer package + separate repo.
5. **M-004** — Two-Axis Translation × Revisions. Most dependency-blocked; files now for visibility but does not start work until M-001 complete and `listing-pipeline-v1.md` (TBD) ships to WP07-ready state.

## Agent assignments (uniform across all missions)

```yaml
implementer: sonnet
reviewer: opus
escalation_after_n_rejections: 2
escalation_target: opus-as-implementer
```

Per Spec Kitty implement-review loop:
- Sonnet receives a WP brief and produces an implementation.
- Opus reviews. Approves → WP done. Rejects with feedback → Sonnet revises.
- After two consecutive Opus rejections of Sonnet's work on the same WP, escalation triggers: Opus takes over implementation; the cycle continues with a different (possibly human) reviewer or arbiter.

## Pre-filing readiness checklist

For each mission to be filed:

- [x] Spec drafted and ratified internally (status: `draft-spec`)
- [x] Governing ADRs accepted (010–018, 012 superseded by 012a)
- [x] Charter governs the surface (charter §5 + §3.2 + §10 cross-refs)
- [x] Agent assignments encoded in mission.json + spec YAML metadata
- [x] External dependencies documented in mission.json
- [x] Validation consumer named
- [x] Work-package count and parallelizability documented
- [x] Estimated breaking-change count assessed (all zero — additive surfaces)

## Charter ratification status

- **Charter status (top of `docs/specs/stability-charter.md`):** Ratification-ready
- **§11 questions:** All twelve resolved (2026-05-11)
- **CI infrastructure:** Authored at `.github/workflows/surface-parity.yml` + `changelog-discipline.yml`, scripts at `tools/check-surface-parity.php` + `tools/check-changelog-discipline.sh`
- **Remaining for ratification:** `@jonesrussell` merges the ratification PR with the alpha-train tag commit message per charter §12.4

## What this manifest enables

Once `spec-kitty.specify` is invoked against each `mission.json`, Spec Kitty registers the missions in its runtime state. The `spec-kitty-implement-review` skill then begins the implement-review loop, dispatching Sonnet for implementation and Opus for review per the per-mission `agent_assignments` block.

For autonomous execution: file M-001 first, start its WP01 implement-review loop, and let dependency-aware sequencing advance through the four missions over ~6–9 months of parallel work.

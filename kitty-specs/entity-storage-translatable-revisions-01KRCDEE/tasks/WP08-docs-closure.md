---
work_package_id: WP08
title: "Validation + docs closure: Minoo teaching E2E, canonical doctrine spec, cookbook, upgrade guide, charter §5.3 amendment, surface-map sync, CHANGELOG"
dependencies:
- WP03
- WP04
- WP05
- WP06
- WP07
requirement_refs:
- FR-043
- FR-044
- FR-045
- FR-046
- FR-047
- FR-048
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: main
base_commit: 3b2af0d9aacac8436de314a5a402e1ba24b73cc0
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T046
- T047
- T048
- T049
- T050
- T051
- T052
- T053
shell_pid: "144246"
history: []
authoritative_surface: docs/specs/entity-storage-two-axis.md
execution_mode: code_change
owned_files:
- tests/Integration/Phase29/MinooTeachingTwoAxisE2ETest.php
- docs/specs/entity-storage-two-axis.md
- docs/specs/entity-storage-translatable-revisions.md
- docs/specs/stability-charter.md
- docs/specs/public-surface-map.md
- docs/public-surface-map.php
- docs/cookbook/translatable-revisionable-entities.md
- docs/upgrade-notes/two-axis-storage.md
- CLAUDE.md
- CHANGELOG.md
agent: "claude:opus:python-reviewer:reviewer"
---

# Work Package Prompt: WP08 — Validation + docs closure (Minoo teaching E2E + canonical doctrine + cookbook + surface-map)

## Mission context

- **Mission:** M-004 — Entity Storage Translatable Revisions (`entity-storage-translatable-revisions-01KRCDEE`)
- **Spec:** [`../spec.md`](../spec.md) §3.10 (validation), §3.11 (documentation), §8 (acceptance), §4 (stable surface deliverables)
- **Plan:** [`../plan.md`](../plan.md)

## Summary

Close the mission with the Minoo `teaching` end-to-end validation (FR-043 + FR-044) and the documentation deliverables: canonical doctrine spec, cookbook (operator guide with performance guidance), upgrade-guide entry, charter §5.3 amendment, and public-surface-map registration of the two-axis schema shape + `SaveContext::withTranslations` + `StorageMigrationException` + new `historicalRevisionWrite` factory. CHANGELOG `[Unreleased]` bullet. CLAUDE.md orchestration row update for the two-axis paths.

## Requirements covered

- FR-043 — Minoo `teaching` round-trip: 5 revisions across English + Anishinaabemowin with independent sequencing; non-translatable field change propagates via fallback
- FR-044 — per-language access policy fixture: Coordinator sees English-only history; Knowledge-Keeper sees both
- FR-045 — `docs/specs/entity-storage-two-axis.md` ships as canonical spec
- FR-046 — `docs/cookbook/translatable-revisionable-entities.md` ships (operator guide + performance guidance)
- FR-047 — upgrade-guide entry for the alpha train that introduces two-axis support (per charter §7)
- FR-048 — cross-references from `entity-storage-v2.md` and from `entity-storage-translatable-revisions.md` to the new canonical spec

## Dependencies

This WP depends on: WP03, WP04, WP05, WP06, WP07 (closes the mission).

## Subtasks

- T046 — Implement `MinooTeachingTwoAxisE2ETest`: create teaching in English; add Anishinaabemowin translation; edit English 3 times → 3 new English revisions, 1 Anishinaabemowin revision; edit Anishinaabemowin 2 times → 2 new Anishinaabemowin revisions, English unchanged; verify revision-list output (5 revisions, independent sequencing); verify non-translatable field propagation via fallback (FR-043).
- T047 — Extend the same E2E with the Coordinator vs Knowledge-Keeper fixture (FR-044).
- T048 — Write `docs/specs/entity-storage-two-axis.md` (FR-045) — canonical doctrine spec; schema shapes, save/load algorithms, exception surface, listing integration, performance notes.
- T049 — Update `docs/specs/entity-storage-translatable-revisions.md` with post-mortem stamp + cross-link (FR-048).
- T050 — Write `docs/cookbook/translatable-revisionable-entities.md` (FR-046) — when to opt in, access composition, performance implications (non-translatable-field fallback cost, multi-language atomic save lock footprint, pruning as near-mandatory practice for high-edit entities).
- T051 — Write `docs/upgrade-notes/two-axis-storage.md` (FR-047).
- T052 — Amend `docs/specs/stability-charter.md` §5.3 with two-axis schema shape, `SaveContext::withTranslations`, `RevisionableEntityInterface::listRevisions($langcode = null)`, `EntityTranslationException::historicalRevisionWrite()`, `StorageMigrationException`. Update `docs/specs/public-surface-map.md` + `docs/public-surface-map.php` mirror.
- T053 — Update `CLAUDE.md` orchestration table row for `packages/entity-storage/*` two-axis paths; add `CHANGELOG.md` `[Unreleased]` bullet.

## Owned files

- `tests/Integration/Phase29/MinooTeachingTwoAxisE2ETest.php`
- `docs/specs/entity-storage-two-axis.md`
- `docs/specs/entity-storage-translatable-revisions.md`
- `docs/specs/stability-charter.md`
- `docs/specs/public-surface-map.md`
- `docs/public-surface-map.php`
- `docs/cookbook/translatable-revisionable-entities.md`
- `docs/upgrade-notes/two-axis-storage.md`
- `CLAUDE.md`
- `CHANGELOG.md`

## Acceptance

- Minoo `teaching` E2E (FR-043) passes in CI.
- Per-language access fixture (FR-044) passes in CI.
- Canonical spec (FR-045) and cookbook (FR-046) ship.
- Charter §5.3 amendment lands; `public-surface-map.md` + `public-surface-map.php` registered with `tier: stable` and `mission_status: present`.
- CHANGELOG `[Unreleased]` Added bullet present.
- `composer phpstan` (level 5) green; `composer cs-check` clean; `tools/drift-detector.sh` green for `docs/specs/entity-storage-translatable-revisions.md`.
- No modifications outside `owned_files`.

## Activity Log

(populated by implement-review loop)
- 2026-05-17T03:59:34Z – claude:sonnet:python-implementer:implementer – shell_pid=140368 – Started implementation via action command
- 2026-05-17T04:16:20Z – claude:sonnet:python-implementer:implementer – shell_pid=140368 – WP08 ready: M-004 doc closure + WP01 marker swap
- 2026-05-17T04:17:02Z – claude:opus:python-reviewer:reviewer – shell_pid=144246 – Started review via action command

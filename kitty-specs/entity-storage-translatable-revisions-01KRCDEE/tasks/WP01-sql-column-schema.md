---
work_package_id: WP01
title: 'sql-column two-axis schema: composite (tid, langcode, vid) PK in RevisionTableBuilder'
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-004
- FR-005
- FR-006
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-entity-storage-translatable-revisions-01KRCDEE
base_commit: e20d3fea886b2b5a74c7d7020047e65e9529e377
created_at: '2026-05-17T02:31:39.468774+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
agent: "claude:opus:python-reviewer:reviewer"
shell_pid: "119512"
history: []
authoritative_surface: packages/entity-storage/src/Schema/RevisionTableBuilder.php
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Schema/RevisionTableBuilder.php
- packages/entity-storage/tests/Contract/TwoAxisStorageContract.php
- packages/entity-storage/tests/Contract/SqlColumnTwoAxisStorageTest.php
- packages/entity-storage/tests/Unit/Schema/RevisionTableBuilderTwoAxisTest.php
- tests/Integration/Phase29/TwoAxisSchemaIntegrationTest.php
---

# Work Package Prompt: WP01 — sql-column two-axis schema (composite PK)

## Mission context

- **Mission:** M-004 — Entity Storage Translatable Revisions (`entity-storage-translatable-revisions-01KRCDEE`)
- **Spec:** [`../spec.md`](../spec.md) §3.1 (Schema FRs), §5.1 (sql-column shape), §12.3 (R-02 extend not fork)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADRs:** ADR 016 (revisions), ADR 017 (per-field translation)

## Summary

Extend `RevisionTableBuilder` from its current surrogate `vid INTEGER PRIMARY KEY` shape (M-006 single-axis) to also emit a composite `(tid, langcode, vid)` revision table when the entity type is both revisionable and translatable. Single-axis types retain the surrogate PK output byte-for-byte (R-A risk mitigation). Add the boot-time guard rejecting translatable fields on vector / remote backends (FR-006 / `StorageMigrationException::unsupportedTwoAxisField` — exception class lives in WP04).

## Requirements covered

- FR-001 — composite-PK shape `(entity_id, langcode, vid)`
- FR-002 — sql-column table layout (primary + translation + revision + translation-revision)
- FR-004 — non-translatable fields stored once on default-langcode revision
- FR-005 — single-step fallback for non-translatable reads from non-default langcode
- FR-006 — vector / remote backend guard for translatable fields
- FR-008 — entity-level primary current revision = default-langcode current revision

## Dependencies

This WP depends on: none (entry point alongside WP02).

## Subtasks

- T001 — Extend `RevisionTableBuilder` constructor / per-call flag to emit composite-PK shape when entity type is both revisionable + translatable (FR-001, FR-002).
- T002 — Implement non-translatable-field allocation rule: column lives on `<table>__revision`, not `<table>__translation__revision` (FR-004).
- T003 — Add boot-time guard rejecting translatable fields on vector / remote backends (FR-006); raises `StorageMigrationException::unsupportedTwoAxisField` (factory delivered in WP04 — depend on its existence at integration time).
- T004 — Implement entity-level primary current-revision invariant: `<table>.vid` mirrors `<table>__translation.vid` for `(tid, default_langcode)` (FR-008).
- T005 — Implement single-step fallback for non-translatable field reads from a non-default langcode (FR-005).
- T006 — Write `TwoAxisStorageContract` abstract test (CoversNothing) covering schema invariants common to both backends.
- T007 — Write `SqlColumnTwoAxisStorageTest` concrete contract subclass + `RevisionTableBuilderTwoAxisTest` + `TwoAxisSchemaIntegrationTest` (Phase 29).

## Owned files

- `packages/entity-storage/src/Schema/RevisionTableBuilder.php`
- `packages/entity-storage/tests/Contract/TwoAxisStorageContract.php`
- `packages/entity-storage/tests/Contract/SqlColumnTwoAxisStorageTest.php`
- `packages/entity-storage/tests/Unit/Schema/RevisionTableBuilderTwoAxisTest.php`
- `tests/Integration/Phase29/TwoAxisSchemaIntegrationTest.php`

## Acceptance

- M-006 single-axis revision-table tests (`SingleAxisRevisionTableBuilderTest`) continue to pass unchanged.
- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files`.

## Activity Log

(populated by implement-review loop)
- 2026-05-17T02:31:40Z – claude:sonnet:python-implementer:implementer – shell_pid=116371 – Assigned agent via action command
- 2026-05-17T02:44:17Z – claude:sonnet:python-implementer:implementer – shell_pid=116371 – WP01 ready: two-axis sql-column schema (composite PK via surrogate vid + UNIQUE (entity_id, langcode, vid)); FR-001/002/004/005/006/008 covered by 21 new tests; M-006 single-axis tests pass unchanged (8 tests). Commit 7d46f164c.
- 2026-05-17T02:44:56Z – claude:opus:python-reviewer:reviewer – shell_pid=119512 – Started review via action command
- 2026-05-17T02:47:18Z – claude:opus:python-reviewer:reviewer – shell_pid=119512 – WP01 review passed: single-axis build() byte-for-byte unchanged (M-006 8/8 regression green); buildTwoAxis() emits __revision + __translation__revision siblings; surrogate vid PK + UNIQUE (entity_id, langcode, vid) at line 454; FR-006 boot guard raises RuntimeException with unsupportedTwoAxisField marker for WP04 swap; sql-column contract test foundation in place for WP02 sql-blob; 21 new tests pass (8 unit + 9 contract + 2 Phase29 + 2 doc cross-refs); 585 entity-storage tests pass; cs-check, phpstan, package-layers all clean. Scope strictly entity-storage src + tests.
- 2026-05-17T04:20:12Z – claude:opus:python-reviewer:reviewer – shell_pid=119512 – Done override: M-004 merged to main as 70b867c39

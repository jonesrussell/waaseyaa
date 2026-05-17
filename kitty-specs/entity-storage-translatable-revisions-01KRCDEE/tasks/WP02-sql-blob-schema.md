---
work_package_id: WP02
title: "sql-blob two-axis schema: per-revision blob rows via TranslationSchemaHandler extension"
dependencies: []
requirement_refs:
- FR-001
- FR-003
- FR-005
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: main
base_commit: 3b2af0d9aacac8436de314a5a402e1ba24b73cc0
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T008
- T009
- T010
- T011
- T012
- T013
shell_pid: "120671"
history: []
authoritative_surface: packages/entity-storage/src/Schema/TranslationSchemaHandler.php
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Schema/TranslationSchemaHandler.php
- packages/entity-storage/src/RevisionableSqlBlobStorage.php
- packages/entity-storage/tests/Contract/SqlBlobTwoAxisStorageTest.php
- packages/entity-storage/tests/Unit/Schema/TranslationSchemaHandlerTwoAxisTest.php
- packages/entity-storage/tests/Unit/RevisionableSqlBlobStorageTwoAxisTest.php
agent: "claude:sonnet:python-implementer:implementer"
---

# Work Package Prompt: WP02 — sql-blob two-axis schema (per-revision blob rows)

## Mission context

- **Mission:** M-004 — Entity Storage Translatable Revisions (`entity-storage-translatable-revisions-01KRCDEE`)
- **Spec:** [`../spec.md`](../spec.md) §3.1, §5.2 (sql-blob shape), §12.1 (M-006 substrate)
- **Plan:** [`../plan.md`](../plan.md)

## Summary

Extend M-006's `TranslationSchemaHandler` (sql-blob path) and `RevisionableSqlBlobStorage` to emit per-revision blob rows for two-axis entity types. Single table `<table>__translation__revision` keyed `(vid)` with composite uniqueness `(tid, langcode, vid)`; the `_data` blob carries the per-langcode translatable-field payload. The primary table `<table>` keeps the default-langcode-current `_data` for non-translatable fields (FR-004 storage rule honored from the blob side too). Single-axis translatable-only and revisionable-only paths preserved unchanged.

## Requirements covered

- FR-001 — composite-key uniqueness on `(tid, langcode, vid)`
- FR-003 — sql-blob backend shape (primary + per-revision blob translation table)
- FR-005 — single-step fallback for non-translatable reads from non-default langcode (blob variant)
- FR-008 — entity-level primary current-revision invariant (default-langcode pointer)

## Dependencies

This WP depends on: none (entry point alongside WP01).

## Subtasks

- T008 — Extend `TranslationSchemaHandler` so a revisionable + translatable entity type emits per-revision blob rows (new shape) while preserving the single-axis blob shape (FR-003).
- T009 — Implement composite-uniqueness index `(tid, langcode, vid)` on the per-revision blob table (FR-001).
- T010 — Extend `RevisionableSqlBlobStorage::writeRevision()` to dispatch to the per-revision blob row write path when entity type is two-axis (FR-003, FR-008).
- T011 — Implement non-translatable-field fallback read at the blob layer: read non-default-langcode revision joins to the default-langcode current-revision row for non-translatable fields (FR-005).
- T012 — Write `SqlBlobTwoAxisStorageTest` concrete contract subclass (extends `TwoAxisStorageContract` from WP01).
- T013 — Write `TranslationSchemaHandlerTwoAxisTest` + `RevisionableSqlBlobStorageTwoAxisTest`.

## Owned files

- `packages/entity-storage/src/Schema/TranslationSchemaHandler.php`
- `packages/entity-storage/src/RevisionableSqlBlobStorage.php`
- `packages/entity-storage/tests/Contract/SqlBlobTwoAxisStorageTest.php`
- `packages/entity-storage/tests/Unit/Schema/TranslationSchemaHandlerTwoAxisTest.php`
- `packages/entity-storage/tests/Unit/RevisionableSqlBlobStorageTwoAxisTest.php`

## Acceptance

- M-006 single-axis translation blob tests continue to pass unchanged.
- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green.
- No modifications outside `owned_files`.

## Activity Log

(populated by implement-review loop)
- 2026-05-17T02:48:08Z – claude:sonnet:python-implementer:implementer – shell_pid=120671 – Started implementation via action command

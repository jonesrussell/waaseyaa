---
work_package_id: WP04
title: "Load semantics (getTranslation->loadRevision) + StorageMigrationException + historicalRevisionWrite factory + translation deletion"
dependencies:
- WP01
- WP02
requirement_refs:
- FR-015
- FR-016
- FR-017
- FR-018
- FR-019
- FR-034
- FR-035
- FR-036
- FR-040
- FR-041
- FR-042
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: main
base_commit: 3b2af0d9aacac8436de314a5a402e1ba24b73cc0
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T022
- T023
- T024
- T025
- T026
- T027
- T028
- T029
shell_pid: "130062"
history: []
authoritative_surface: packages/entity-storage/src/Exception/StorageMigrationException.php
execution_mode: code_change
owned_files:
- packages/entity-storage/src/Exception/StorageMigrationException.php
- packages/entity/src/Exception/EntityTranslationException.php
- packages/entity-storage/tests/Unit/Exception/StorageMigrationExceptionTest.php
- packages/entity/tests/Unit/Exception/EntityTranslationExceptionHistoricalRevisionWriteTest.php
- packages/entity-storage/tests/Unit/HistoricalRevisionWriteTest.php
- packages/entity-storage/tests/Unit/TwoAxisLoadSemanticsTest.php
- packages/entity-storage/tests/Unit/TwoAxisTranslationDeletionTest.php
- tests/Integration/Phase29/TwoAxisSaveLoadIntegrationTest.php
agent: "claude:opus:python-reviewer:reviewer"
---

# Work Package Prompt: WP04 — Load semantics + exception surface + translation deletion

## Mission context

- **Mission:** M-004 — Entity Storage Translatable Revisions (`entity-storage-translatable-revisions-01KRCDEE`)
- **Spec:** [`../spec.md`](../spec.md) §3.3 (load semantics), §3.7 (translation deletion), §3.9 (error model), §6.3, §12.1 R-06
- **Plan:** [`../plan.md`](../plan.md)
- **Contract:** [`../contracts/exception-surface.md`](../contracts/exception-surface.md)

## Summary

Compose M-006's `TranslatableInterface::getTranslation()` with `RevisionableEntityInterface::loadRevision()` so `$entity->getTranslation('fr')->loadRevision(7)` yields the historical French instance. Saves on a historical instance raise `EntityTranslationException::historicalRevisionWrite($vid, $langcode)` (new factory on the M-006 unified exception class — no separate class per FR-040). Ship one new exception class `StorageMigrationException` (factories: `noOpPromotion($entityType)`, `unsupportedTwoAxisField($fieldName, $backend)`). Implement translation deletion (`removeTranslation`) and default-langcode deletion guard reusing M-006's `cannotRemoveDefault` factory.

## Requirements covered

- FR-015 — `$storage->load()` returns entity at default-langcode current revision
- FR-016 — `getTranslation($langcode)` returns entity with that langcode's current revision
- FR-017 — `getTranslation->loadRevision($vid)` historical instance; save raises `historicalRevisionWrite`
- FR-018 — `listRevisions(?$langcode)` interleaved or langcode-scoped
- FR-019 — `translations()` excludes fully-pruned languages
- FR-034 — `removeTranslation($langcode)` deletes the (entity, langcode) row + all its revisions
- FR-035 — removing default-langcode raises `EntityTranslationException::cannotRemoveDefault` (M-006 factory reused)
- FR-036 — removing a non-default translation does not affect other-language revisions or the entity itself
- FR-040 — single unified `EntityTranslationException` + one new `StorageMigrationException`
- FR-041 — stable string `code` field per factory
- FR-042 — factory rename/removal follows charter §4 deprecation cycle

## Dependencies

This WP depends on: WP01, WP02 (schema substrate must land first).

## Subtasks

- T022 — Add `EntityTranslationException::historicalRevisionWrite($vid, $langcode)` factory with stable code `'historical_revision_write'` (FR-017, FR-040, FR-041).
- T023 — Implement `StorageMigrationException` class with `noOpPromotion($entityType)` and `unsupportedTwoAxisField($fieldName, $backend)` factories; stable codes (FR-040, FR-041).
- T024 — Implement load-at-default-langcode-current-revision semantics in storage layer (FR-015).
- T025 — Implement `getTranslation($langcode)` switches active langcode + reads per-`(tid, langcode)` current pointer (FR-016).
- T026 — Implement historical-instance state: `loadRevision($vid)` flips `isCurrentRevision()=false`; `save()` raises `historicalRevisionWrite` (FR-017).
- T027 — Implement `listRevisions(?$langcode)` (interleaved descending vs langcode-scoped) and `translations()` filter excluding fully-pruned languages (FR-018, FR-019).
- T028 — Implement `removeTranslation($langcode)` cascade delete + default-langcode guard (FR-034, FR-035, FR-036).
- T029 — Write tests: `StorageMigrationExceptionTest`, `EntityTranslationExceptionHistoricalRevisionWriteTest`, `HistoricalRevisionWriteTest`, `TwoAxisLoadSemanticsTest`, `TwoAxisTranslationDeletionTest`, `TwoAxisSaveLoadIntegrationTest` (Phase 29).

## Owned files

- `packages/entity-storage/src/Exception/StorageMigrationException.php`
- `packages/entity/src/Exception/EntityTranslationException.php`
- `packages/entity-storage/tests/Unit/Exception/StorageMigrationExceptionTest.php`
- `packages/entity/tests/Unit/Exception/EntityTranslationExceptionHistoricalRevisionWriteTest.php`
- `packages/entity-storage/tests/Unit/HistoricalRevisionWriteTest.php`
- `packages/entity-storage/tests/Unit/TwoAxisLoadSemanticsTest.php`
- `packages/entity-storage/tests/Unit/TwoAxisTranslationDeletionTest.php`
- `tests/Integration/Phase29/TwoAxisSaveLoadIntegrationTest.php`

## Acceptance

- M-006's existing 5 `EntityTranslationException` factories untouched; only new factory added.
- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files`.

## Activity Log

(populated by implement-review loop)
- 2026-05-17T03:16:21Z – claude:sonnet:python-implementer:implementer – shell_pid=128157 – Started implementation via action command
- 2026-05-17T03:24:27Z – claude:sonnet:python-implementer:implementer – shell_pid=128157 – WP04 ready: exception surface (EntityTranslationException::historicalRevisionWrite + new StorageMigrationException) + load/delete semantics tests + Phase29 integration. 1149 tests pass; cs/phpstan/policy/layers green.
- 2026-05-17T03:25:00Z – claude:opus:python-reviewer:reviewer – shell_pid=130062 – Started review via action command

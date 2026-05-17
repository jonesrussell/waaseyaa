---
work_package_id: WP06
title: "Migration generator: extend AddTranslationsMigrationGenerator for two-axis + new AddRevisionsMigrationGenerator (--add-revisions)"
dependencies:
- WP01
- WP02
requirement_refs:
- FR-025
- FR-026
- FR-027
- FR-028
- FR-029
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: main
base_commit: 3b2af0d9aacac8436de314a5a402e1ba24b73cc0
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T035
- T036
- T037
- T038
- T039
- T040
shell_pid: "135930"
history: []
authoritative_surface: packages/cli/src/Handler/AddRevisionsMigrationGenerator.php
execution_mode: code_change
owned_files:
- packages/cli/src/Handler/AddTranslationsMigrationGenerator.php
- packages/cli/src/Handler/AddRevisionsMigrationGenerator.php
- packages/cli/tests/Handler/AddTranslationsMigrationGeneratorTwoAxisTest.php
- packages/cli/tests/Handler/AddRevisionsMigrationGeneratorTest.php
- tests/Integration/Phase29/TwoAxisMigrationGeneratorIntegrationTest.php
agent: "claude:opus:python-reviewer:reviewer"
---

# Work Package Prompt: WP06 — Migration generator (two-axis promotion + --add-revisions)

## Mission context

- **Mission:** M-004 — Entity Storage Translatable Revisions (`entity-storage-translatable-revisions-01KRCDEE`)
- **Spec:** [`../spec.md`](../spec.md) §3.5 (migration generator), §12.1 R-09
- **Plan:** [`../plan.md`](../plan.md)
- **Contract:** [`../contracts/two-axis-migration.md`](../contracts/two-axis-migration.md)

## Summary

Extend M-006's `AddTranslationsMigrationGenerator` so `--add-translations` on a revisionable-only type emits a two-axis migration (composite-PK translation-revision table + per-langcode current-revision pointer + backfill of existing revisions as default-langcode revisions). Ship a sibling `AddRevisionsMigrationGenerator` (new class) for the new `--add-revisions` flag covering translatable-only → two-axis promotion (adds `vid` to existing translation tables, creates the translation-revision table, backfills current translation rows as initial revisions per langcode). Both promotions reversible by default (with documented data loss). Already-two-axis promotion attempts raise `StorageMigrationException::noOpPromotion` (factory delivered by WP04).

## Requirements covered

- FR-025 — `--add-translations` extended for revisionable-only → two-axis; new `--add-revisions` flag
- FR-026 — promote revisionable-only → two-axis (backfill existing revisions as default-langcode revisions)
- FR-027 — promote translatable-only → two-axis (add `vid`, create translation-revision table, backfill current translation rows)
- FR-028 — both promotions reversible by default; reverse loses non-current revisions (documented in migration docblock)
- FR-029 — promoting an already-two-axis type raises `StorageMigrationException::noOpPromotion`

## Dependencies

This WP depends on: WP01, WP02 (schema substrate). Implementation-time dependency on WP04's `StorageMigrationException` for `noOpPromotion` factory.

## Subtasks

- T035 — Detect target shape (non-translatable-non-revisionable / revisionable-only / translatable-only / two-axis) in `AddTranslationsMigrationGenerator` (FR-025).
- T036 — Extend `AddTranslationsMigrationGenerator` to emit two-axis migration when target is revisionable-only (FR-026).
- T037 — Ship `AddRevisionsMigrationGenerator` new class for `--add-revisions` flag covering translatable-only → two-axis path (FR-025, FR-027).
- T038 — Implement reverse migration emit for both promotions with data-loss docblock (FR-028).
- T039 — Raise `StorageMigrationException::noOpPromotion($entityType)` when target is already two-axis (FR-029).
- T040 — Write `AddTranslationsMigrationGeneratorTwoAxisTest`, `AddRevisionsMigrationGeneratorTest`, `TwoAxisMigrationGeneratorIntegrationTest` (Phase 29).

## Owned files

- `packages/cli/src/Handler/AddTranslationsMigrationGenerator.php`
- `packages/cli/src/Handler/AddRevisionsMigrationGenerator.php`
- `packages/cli/tests/Handler/AddTranslationsMigrationGeneratorTwoAxisTest.php`
- `packages/cli/tests/Handler/AddRevisionsMigrationGeneratorTest.php`
- `tests/Integration/Phase29/TwoAxisMigrationGeneratorIntegrationTest.php`

## Acceptance

- M-006's single-axis `--add-translations` path (non-revisionable target) continues to behave identically.
- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green.
- No modifications outside `owned_files`.

## Activity Log

(populated by implement-review loop)
- 2026-05-17T03:36:35Z – claude:sonnet:python-implementer:implementer – shell_pid=133699 – Started implementation via action command
- 2026-05-17T03:45:52Z – claude:sonnet:python-implementer:implementer – shell_pid=133699 – WP06 ready: migration --add-revisions flag
- 2026-05-17T03:46:26Z – claude:opus:python-reviewer:reviewer – shell_pid=135930 – Started review via action command
- 2026-05-17T03:47:51Z – claude:opus:python-reviewer:reviewer – shell_pid=135930 – WP06 review passed: AddRevisionsMigrationGenerator new + AddTranslationsMigrationGenerator extended (M-006 render() zero deletions, byte-for-byte preserved). FR-025 dispatch matrix complete (single-axis rev + single-axis trans + already-two-axis). FR-028 DATA LOSS docblocks present (9 total). FR-029 noOpPromotion idempotency covered in 2 test files. 17 new tests (13 handler + 4 Phase29 integration). Suites: cli 13/13, Phase29 4/4, migration 370/370, entity-storage 659/659. Gates: cs-check + phpstan + check-package-layers all green. Scope clean (cli + Phase29 only).

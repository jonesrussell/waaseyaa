---
work_package_id: WP03
title: "SaveContext::withTranslations(array) builder + langcode-pinned writeRevision; pruning extension"
dependencies:
- WP01
- WP02
requirement_refs:
- FR-007
- FR-009
- FR-010
- FR-011
- FR-012
- FR-013
- FR-014
- FR-037
- FR-038
- FR-039
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: main
base_commit: 3b2af0d9aacac8436de314a5a402e1ba24b73cc0
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T014
- T015
- T016
- T017
- T018
- T019
- T020
- T021
shell_pid: "124451"
history: []
authoritative_surface: packages/entity-storage/src/SaveContext.php
execution_mode: code_change
owned_files:
- packages/entity-storage/src/SaveContext.php
- packages/entity-storage/src/Driver/RevisionableStorageDriver.php
- packages/entity-storage/src/Revision/RevisionPruningPolicy.php
- packages/entity-storage/tests/Unit/SaveContextTranslationsTest.php
- packages/entity-storage/tests/Unit/Driver/RevisionableStorageDriverTwoAxisTest.php
- packages/entity-storage/tests/Unit/Revision/RevisionPruningPolicyTwoAxisTest.php
- tests/Integration/Phase29/TwoAxisSaveLifecycleIntegrationTest.php
agent: "claude:sonnet:python-implementer:implementer"
---

# Work Package Prompt: WP03 — Save semantics (SaveContext::withTranslations, langcode-pinned write, pruning)

## Mission context

- **Mission:** M-004 — Entity Storage Translatable Revisions (`entity-storage-translatable-revisions-01KRCDEE`)
- **Spec:** [`../spec.md`](../spec.md) §3.2 (save semantics), §3.8 (pruning), §6.1–§6.2 (algorithms), §12.1 R-04
- **Plan:** [`../plan.md`](../plan.md)
- **Contract:** [`../contracts/save-context-translations.md`](../contracts/save-context-translations.md)

## Summary

Add `SaveContext::withTranslations(array $langcodes)` builder (note: `withLangcode()` already shipped by M-006). Extend `RevisionableStorageDriver::writeRevision()` to accept an optional `?string $langcode` argument; per-translation save creates a new revision in `<table>__translation__revision` and updates the per-`(tid, langcode)` current-revision pointer in isolation. Multi-language atomic save iterates the langcode list inside a single transaction with `PartialSaveException` on partial failure (FR-013). Lifecycle events fire per saved langcode, with `AfterSaveEvent::affectedLangcodes()` carrying the full list. Extend `RevisionPruningPolicy` for per-language keep-counts (FR-037..FR-039) — never deletes the current revision of any language (FR-038); no-op by default (FR-039).

## Requirements covered

- FR-007 — per-`(entity, langcode)` current-revision pointer tracking
- FR-009 — save of a translation creates only that langcode's revision
- FR-010 — other-language current pointers unchanged
- FR-011 — non-translatable mutation creates new default-langcode revision
- FR-012 — `SaveContext::langcode` field (M-006) used; `activeLangcode()` fallback
- FR-013 — `SaveContext::withTranslations(array)` + `PartialSaveException` on partial failure
- FR-014 — lifecycle events per langcode; `affectedLangcodes()` propagation (M-006 contract preserved)
- FR-037 — pruning policies extensible per-language
- FR-038 — pruning never deletes the current revision of any language
- FR-039 — pruning no-op by default; explicit opt-in

## Dependencies

This WP depends on: WP01, WP02 (schema substrate must land first).

## Subtasks

- T014 — Add `SaveContext::withTranslations(array $langcodes)` immutable builder; reject empty arrays via validator; document mutual exclusivity with `withLangcode` (T014).
- T015 — Extend `RevisionableStorageDriver::writeRevision()` signature with `?string $langcode = null`; dispatch to per-`(tid, langcode)` write path when set (FR-007, FR-009).
- T016 — Implement non-translatable-field change detection: if changed, allocate a new default-langcode revision row (FR-011).
- T017 — Implement other-language current-pointer invariance (FR-010) and per-language current-revision pointer update (FR-007).
- T018 — Implement multi-language atomic save loop in a single transaction with rollback on failure (`PartialSaveException`) (FR-013).
- T019 — Fire `BeforeSaveEvent` / `AfterSaveEvent` per saved langcode; populate `affectedLangcodes()` (FR-014).
- T020 — Extend `RevisionPruningPolicy` for per-language keep-counts; enforce never-delete-current-revision invariant; default no-op (FR-037, FR-038, FR-039).
- T021 — Write unit + integration tests: `SaveContextTranslationsTest`, `RevisionableStorageDriverTwoAxisTest`, `RevisionPruningPolicyTwoAxisTest`, `TwoAxisSaveLifecycleIntegrationTest` (Phase 29).

## Owned files

- `packages/entity-storage/src/SaveContext.php`
- `packages/entity-storage/src/Driver/RevisionableStorageDriver.php`
- `packages/entity-storage/src/Revision/RevisionPruningPolicy.php`
- `packages/entity-storage/tests/Unit/SaveContextTranslationsTest.php`
- `packages/entity-storage/tests/Unit/Driver/RevisionableStorageDriverTwoAxisTest.php`
- `packages/entity-storage/tests/Unit/Revision/RevisionPruningPolicyTwoAxisTest.php`
- `tests/Integration/Phase29/TwoAxisSaveLifecycleIntegrationTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- M-006 `SaveContext::withLangcode()` tests continue to pass unchanged.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green.
- No modifications outside `owned_files`.

## Activity Log

(populated by implement-review loop)
- 2026-05-17T03:02:22Z – claude:sonnet:python-implementer:implementer – shell_pid=124451 – Started implementation via action command
- 2026-05-17T03:13:43Z – claude:sonnet:python-implementer:implementer – shell_pid=124451 – WP03 ready: SaveContext withTranslations + atomic two-axis save semantics; 36 new tests pass; all gates green; commit d25c48f64

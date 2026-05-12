---
work_package_id: WP07
title: SaveContext::withLangcode + coordinator write-semantics matrix
dependencies:
- WP04
- WP05
requirement_refs:
- FR-033
- FR-034
- FR-035
- FR-036
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T034
- T035
- T036
- T037
- T038
- T039
history: []
authoritative_surface: packages/entity-storage/
execution_mode: code_change
owned_files:
- packages/entity-storage/src/SaveContext.php
- packages/entity-storage/src/EntityStorageCoordinator.php
- packages/entity-storage/tests/Coordinator/Translation*
- packages/entity-storage/tests/SaveContext*
tags: []
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "590932"
---

# WP07 — SaveContext::withLangcode + coordinator write-semantics matrix

## Objective

Extend `SaveContext` with `withLangcode(string)` and implement the coordinator's 8-cell write-semantics matrix (new × existing × default × non-default × pending-remove). All writes wrapped in a single `UnitOfWork::transaction`.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.6 (FR-033..FR-036), §7.3 (Write semantics flow)
- **Data model:** [`../data-model.md`](../data-model.md) "Write-semantics decision matrix"
- **Existing code:** `packages/entity-storage/src/SaveContext.php` (current shape with `withoutNewRevision`).

## Subtasks

### T034 — `SaveContext::withLangcode` builder

**Steps:**

1. Open `packages/entity-storage/src/SaveContext.php`. Current constructor takes `bool $withoutNewRevision = false`.
2. Modify to:
   ```php
   final class SaveContext
   {
       private function __construct(
           public readonly bool $withoutNewRevision = false,
           public readonly ?string $langcode = null,
       ) {}

       public static function default(): self
       {
           return new self();
       }

       public function withoutNewRevision(): self
       {
           return new self(withoutNewRevision: true, langcode: $this->langcode);
       }

       public function withLangcode(string $langcode): self
       {
           return new self(withoutNewRevision: $this->withoutNewRevision, langcode: $langcode);
       }
   }
   ```

**Files:** `packages/entity-storage/src/SaveContext.php` (modify, ~20 lines).

### T035 — Coordinator: `langcodeRequired()` for unset default_langcode

**Steps:**

1. In `EntityStorageCoordinator::write()` (or equivalent save method), at the top:
   ```php
   if ($entity instanceof TranslatableInterface) {
       try {
           $entity->defaultLangcode();
       } catch (EntityTranslationException) {
           throw EntityTranslationException::langcodeRequired();
       }
   }
   ```
2. The trait's `defaultLangcode()` (WP01) throws `langcodeRequired()` when unset; the coordinator re-throws or surfaces the same exception type.

**Files:** `packages/entity-storage/src/EntityStorageCoordinator.php` (modify, ~10 lines).

### T036 — Honor `SaveContext::langcode`

**Steps:**

1. Resolve the effective langcode:
   ```php
   $lc = $context->langcode ?? $entity->activeLangcode();
   ```
2. Route writes based on `$lc`, the entity's translation state, and the field's translatability (read in WP04/WP05 backends).

**Files:** Coordinator (~20 lines added).

### T037 — 8-cell write-semantics matrix

**Steps:**

Implement the cases enumerated in `data-model.md` "Write-semantics decision matrix":

| Case | T | N | L vs D | Behaviour |
|---|---|---|---|---|
| 1 | false | * | n/a | Existing single-table path |
| 2 | true | true | L == D | INSERT primary + INSERT default-translation row, dispatch entity events |
| 3 | true | true | L ≠ D | Case 2 + INSERT non-default translation row, dispatch TRANSLATION_INSERT |
| 4 | true | false | L == D | UPDATE primary (non-translatable) + UPDATE default-translation (translatable), dispatch entity events |
| 5 | true | false | L ≠ D, has(L) | UPDATE primary + UPDATE translation(L), dispatch entity + TRANSLATION_UPDATE |
| 6 | true | false | L ≠ D, !has(L) | UPDATE primary + INSERT translation(L), dispatch entity + TRANSLATION_INSERT |
| 7 | true | false | pending remove(R) | Case 4/5 + DELETE translation(R), dispatch TRANSLATION_DELETE |
| 8 | true | * | default_langcode unset | T035 throw |

For each case, dispatch the right lifecycle events (WP08 ships event classes; coordinator dispatches them — coordinate sequencing).

**Files:** Coordinator (~100 lines added).

### T038 — Apply pending removeTranslation deletions

**Steps:**

1. After the main save path:
   ```php
   if ($entity instanceof TranslatableInterface) {
       $pendingRemovals = $entity->_takePendingTranslationDeletions();
       foreach ($pendingRemovals as $lc) {
           $this->backend->deleteTranslation($entity, $lc);
           // Dispatch PRE/POST_TRANSLATION_DELETE (WP08)
       }
   }
   ```
2. All this MUST run inside the same `UnitOfWork::transaction` as the primary save.

**Files:** Coordinator (~30 lines added).

### T039 — Integration tests

**Steps:**

1. Create `packages/entity-storage/tests/Coordinator/TranslationWriteSemanticsTest.php`. One method per case in the matrix.
2. Create `packages/entity-storage/tests/SaveContext/WithLangcodeTest.php` for the value-object behaviour.
3. Use SQLite in-memory + the fixture entity type.

**Files:** ~400 lines of tests.

## Definition of Done

- [ ] `SaveContext::withLangcode(string)` builder + `?string $langcode` property.
- [ ] Coordinator refuses to save translatable entity with unset `default_langcode`.
- [ ] All 8 matrix cases implemented and tested.
- [ ] Pending translation deletions applied in same transaction.
- [ ] No regression on non-translatable types (case 1 unchanged).
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| Coordinator complexity proliferation. Matrix has 8 cells; getting any wrong causes silent data divergence. | One test per cell; data-model.md is normative. |
| Event dispatching happens here BUT event classes ship in WP08. | Coordinate: WP07 places dispatcher calls; WP08 ships the event classes. WP08 depends on WP07 in the lane. |
| `_takePendingTranslationDeletions()` is `@internal` (WP01). Coordinator is the legitimate caller. | Document the call site. |

## Reviewer guidance

- Walk the test matrix; verify one test per case.
- Verify atomic transaction wrapping (a failure in case 7's translation delete must roll back the primary update).
- Verify the coordinator does NOT use `default_langcode` as a fallback when `SaveContext::langcode` is null — it uses `activeLangcode()`.

## Implementation command

```bash
spec-kitty agent action implement WP07 --agent <name>
```

## Activity Log

- 2026-05-12T23:19:07Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=586095 – Started implementation via action command
- 2026-05-12T23:26:32Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=586095 – SaveContext::withLangcode + write-semantics matrix tests across 8 cells x 2 backends
- 2026-05-12T23:27:05Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=590932 – Started review via action command

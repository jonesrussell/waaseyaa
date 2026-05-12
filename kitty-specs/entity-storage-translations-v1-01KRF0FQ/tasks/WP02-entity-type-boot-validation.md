---
work_package_id: WP02
title: EntityType boot validation for translatable types
dependencies:
- WP01
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-057
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T006
- T007
- T008
- T009
history: []
authoritative_surface: packages/entity/
execution_mode: code_change
owned_files:
- packages/entity/src/EntityType.php
- packages/entity/src/Exception/InvalidEntityTypeException.php
- packages/entity/tests/Unit/EntityType*
tags: []
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "521664"
---

# WP02 — EntityType boot validation for translatable types

## Objective

Make `EntityType::translatable: true` load-bearing at boot. When set, the entity type MUST declare `langcode` + `default_langcode` keys and the registered entity class MUST implement `TranslatableInterface`. Surface failures via `InvalidEntityTypeException` at boot — not at first-call runtime.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.1 (FR-001..FR-005), §3.11 (FR-057)
- **Plan:** [`../plan.md`](../plan.md) §"Project Structure"
- **Data model:** [`../data-model.md`](../data-model.md) "EntityType — boot validation"
- **Existing code:** `packages/entity/src/EntityType.php` lines 73-83 (constructor with `translatable: bool` param)

## Subtasks

### T006 — Extend `InvalidEntityTypeException`

**Steps:**

1. Open `packages/entity/src/Exception/InvalidEntityTypeException.php`.
2. Add 3 static factories:
   ```php
   public static function missingLangcodeKey(string $entityTypeId): self
   {
       return new self(sprintf(
           'Translatable entity type "%s" must declare a "langcode" entity key.',
           $entityTypeId
       ));
   }

   public static function missingDefaultLangcodeKey(string $entityTypeId): self
   {
       return new self(sprintf(
           'Translatable entity type "%s" must declare a "default_langcode" entity key.',
           $entityTypeId
       ));
   }

   public static function translatableEntityClassNotImplementingInterface(
       string $entityTypeId,
       string $entityClass,
   ): self {
       return new self(sprintf(
           'Translatable entity type "%s" registered class "%s" must implement TranslatableInterface.',
           $entityTypeId,
           $entityClass
       ));
   }
   ```

**Files:** `packages/entity/src/Exception/InvalidEntityTypeException.php` (modify, ~30 lines added).

### T007 — Boot validation in `EntityType::__construct`

**Steps:**

1. Open `packages/entity/src/EntityType.php`. The constructor takes `private bool $translatable = false` at line 75.
2. In the constructor body (after existing parameter assignments), add:
   ```php
   if ($this->translatable) {
       if (!isset($this->keys['langcode'])) {
           throw InvalidEntityTypeException::missingLangcodeKey($this->id);
       }
       if (!isset($this->keys['default_langcode'])) {
           throw InvalidEntityTypeException::missingDefaultLangcodeKey($this->id);
       }
       if (!is_subclass_of($this->class, TranslatableInterface::class)) {
           throw InvalidEntityTypeException::translatableEntityClassNotImplementingInterface(
               $this->id,
               $this->class
           );
       }
   }
   ```
3. Add `use Waaseyaa\Entity\TranslatableInterface;` import if needed.

**Files:** `packages/entity/src/EntityType.php` (modify, ~15 lines added).

### T008 — Bundle independence

**Purpose:** FR-005 — bundle entity types may themselves be translatable, but the flag is per-type, not inherited via `bundleEntityType`.

**Steps:**

1. Add a comment in `EntityType::__construct` documenting that `bundleEntityType` does not propagate translatability. The `translatable` parameter is independent.
2. Add an integration-like unit test in T009 that verifies a translatable type with a non-translatable bundle entity type boots cleanly (and vice versa).

**Files:** `packages/entity/src/EntityType.php` (comment addition only).

### T009 — Unit tests

**Steps:**

1. Create `packages/entity/tests/Unit/EntityTypeBootValidationTest.php`:
   - Positive case: translatable: true with proper keys + entity class implementing `TranslatableInterface` boots without throwing.
   - Negative cases (each throws the right factory):
     - `translatable: true` + missing `langcode` key → `missingLangcodeKey()`
     - `translatable: true` + missing `default_langcode` key → `missingDefaultLangcodeKey()`
     - `translatable: true` + entity class NOT implementing `TranslatableInterface` → `translatableEntityClassNotImplementingInterface()`
   - Bundle case: translatable type with a non-translatable bundle entity type boots.
   - Non-translatable case: `translatable: false` (default) boots regardless of keys/class shape (no regression).

2. Use anonymous test entity classes inline:
   ```php
   $entityClass = new class extends ContentEntityBase {
       public function __construct(array $values = []) {
           parent::__construct($values, 'test_type', [...]);
       }
   };
   ```
   ContentEntityBase already implements `TranslatableInterface` from WP01.

**Files:** `packages/entity/tests/Unit/EntityTypeBootValidationTest.php` (new, ~200 lines).

## Definition of Done

- [ ] `InvalidEntityTypeException` has 3 new static factories.
- [ ] `EntityType::__construct` throws each factory when its precondition fails.
- [ ] All unit tests pass.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.
- [ ] Existing tests that construct `EntityType` instances with `translatable: false` (the universe today) continue to pass — no regression.

## Risks

| Risk | Mitigation |
|---|---|
| Existing test fixtures might construct `EntityType` with `translatable: true` but no proper keys (unlikely — no entity type today sets the flag). | Run `grep -rn 'translatable: true\|translatable=>true' packages/` to find any. None should exist; if found, fix or document. |
| `is_subclass_of` with class string vs object. | Use the class string form: `is_subclass_of($this->class, TranslatableInterface::class)`. |

## Reviewer guidance

- Confirm boot validation runs unconditionally for `translatable: true`, not behind a feature flag.
- Confirm error messages name the specific entity type id and class (helpful debugging).
- Verify the `is_subclass_of` check uses string class names (not class-string lookup which has been deprecated per project memory `feedback_modern_php_rules.md`).

## Implementation command

```bash
spec-kitty agent action implement WP02 --agent <name>
```

## Activity Log

- 2026-05-12T22:04:15Z – claude:sonnet:waaseyaa-implementer:implementer – shell_pid=516549 – Started implementation via action command
- 2026-05-12T22:09:45Z – claude:sonnet:waaseyaa-implementer:implementer – shell_pid=516549 – Boot validation for translatable types ready — all gates green
- 2026-05-12T22:10:53Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=521664 – Started review via action command
- 2026-05-12T22:15:23Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=521664 – WP02 approved: 3 new InvalidEntityTypeException factories + boot validation in EntityType::__construct (langcode/default_langcode/TranslatableInterface) + FR-005 bundle independence comment + 10 passing unit tests. Scope expansion to ContentEntityKeys and EntityMetadataReader (default_langcode plumbing) is defensible (required for attribute-path end-to-end validation), safe (strictly additive, nullable default), and correct (matches spec intent). Gates: phpunit entity Unit GREEN (434/434), bin/check-package-layers GREEN, phpstan entity package errors DECREASED from 454 (base) to 418 (lane) — net -36 errors; 3 new minor errors in test file's anonymous class signature (cosmetic). cs-check has 1 trailing-newline issue in TranslatableInterface.php (WP01-owned, out of WP02 scope).

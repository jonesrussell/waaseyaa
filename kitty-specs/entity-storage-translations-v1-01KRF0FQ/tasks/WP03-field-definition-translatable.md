---
work_package_id: WP03
title: FieldDefinition::translatable() builder + per-field flag validation
dependencies:
- WP01
requirement_refs:
- FR-016
- FR-017
- FR-018
- FR-019
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T010
- T011
- T012
- T013
- T014
history: []
authoritative_surface: packages/field/
execution_mode: code_change
owned_files:
- packages/field/src/FieldDefinition.php
- packages/field/src/Exception/InvalidFieldDefinitionException.php
- packages/field/tests/Unit/FieldDefinition*
tags: []
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "532407"
---

# WP03 — FieldDefinition::translatable() builder + per-field flag validation

## Objective

Add the per-field `translatable(bool): self` builder to `FieldDefinition` (which is `final readonly`), plus boot validation that rejects translatable system-key fields and translatable fields on non-translatable entity types.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.3 (FR-016..FR-019)
- **Research:** [`../research.md`](../research.md) R2 (readonly-builder pattern)
- **Existing code:** `packages/field/src/FieldDefinition.php` — `isTranslatable(): bool` getter already at line 78; builder absent. `storedIn()` at line 209 + `indexed()` at line 252 establish the new-self pattern.

## Subtasks

### T010 — Extend `InvalidFieldDefinitionException`

**Steps:**

1. Open `packages/field/src/Exception/InvalidFieldDefinitionException.php`. Add factories:

   ```php
   public static function translatableOnNonTranslatableEntityType(
       string $fieldName,
       string $entityTypeId,
   ): self {
       return new self(sprintf(
           'Field "%s" is marked translatable, but entity type "%s" is not translatable.',
           $fieldName,
           $entityTypeId
       ));
   }

   public static function systemKeyMarkedTranslatable(string $fieldName): self
   {
       return new self(sprintf(
           'System key field "%s" cannot be marked translatable.',
           $fieldName
       ));
   }
   ```

**Files:** `packages/field/src/Exception/InvalidFieldDefinitionException.php` (modify, ~20 lines added).

### T011 — `FieldDefinition::translatable()` builder

**Purpose:** Follow `storedIn()`/`indexed()` pattern. Return a new instance with the `translatable` constructor param swapped.

**Steps:**

1. Open `packages/field/src/FieldDefinition.php`. Locate the constructor (line 15) — it accepts `bool $translatable = false` as a promoted readonly property.
2. Add the builder method after the existing `indexed()` method:

   ```php
   public function translatable(bool $value = true): self
   {
       return new self(
           name: $this->name,
           type: $this->type,
           cardinality: $this->cardinality,
           settings: $this->settings,
           // ... all current constructor params copied here ...
           translatable: $value,
           revisionable: $this->revisionable,
           // ... etc ...
       );
   }
   ```

   Match the EXACT constructor signature of `FieldDefinition`. Inspect lines 15-37 to enumerate all params.

3. The existing `isTranslatable()` getter at line 78 returns `$this->translatable` — unchanged.

**Files:** `packages/field/src/FieldDefinition.php` (modify, ~30 lines added).

### T012 — Validation: translatable field on non-translatable entity type

**Purpose:** FR-017 — a field declaring `translatable: true` on a `translatable: false` entity type MUST raise at boot.

**Steps:**

1. The validation lives where `FieldDefinition` instances get associated with an `EntityType` — typically in `EntityTypeManager::registerFieldDefinitions()` or equivalent. Locate the wiring point.
2. After each `FieldDefinition` is attached to an `EntityType`, check:
   ```php
   if ($fieldDef->isTranslatable() && !$entityType->isTranslatable()) {
       throw InvalidFieldDefinitionException::translatableOnNonTranslatableEntityType(
           $fieldDef->getName(),
           $entityType->id()
       );
   }
   ```
3. If the wiring point doesn't exist as a clean injection site, add a `validate(EntityTypeInterface): void` method on `FieldDefinition` and call it from `EntityType::registerFields()` (or whatever method exists). Document the chosen point.

**Files:** Adjustments to `FieldDefinition.php` AND possibly `EntityType.php` (boot-time field registration). Keep file ownership inside `packages/field/src/` if possible.

**Validation:** A `FieldDefinition` with `translatable: true` registered against a `translatable: false` `EntityType` throws at boot.

### T013 — Validation: system key fields cannot be translatable

**Purpose:** FR-019 — `id`, `uuid`, `langcode`, `default_langcode`, `revision` MUST NOT be translatable.

**Steps:**

1. Maintain a `const SYSTEM_KEYS` array on `FieldDefinition` (or import from `EntityConstants`):
   ```php
   private const SYSTEM_KEYS = ['id', 'uuid', 'langcode', 'default_langcode', 'revision'];
   ```
2. In the same boot-time validation pass:
   ```php
   if ($fieldDef->isTranslatable() && in_array($fieldDef->getName(), self::SYSTEM_KEYS, true)) {
       throw InvalidFieldDefinitionException::systemKeyMarkedTranslatable($fieldDef->getName());
   }
   ```

**Files:** Same as T012.

### T014 — Unit tests

**Steps:**

1. Create `packages/field/tests/Unit/FieldDefinitionTranslatableTest.php`:
   - `translatable()` builder returns a new instance with `isTranslatable() === true`.
   - `translatable(false)` returns a new instance with `isTranslatable() === false`.
   - Default `isTranslatable() === false` for fields constructed without calling the builder.
   - Builder is composable: `(new FieldDefinition(...))->storedIn('sql-column')->translatable()->indexed()` returns a fully-configured field.
   - Original instance unchanged after builder call (readonly invariant).

2. Create `packages/field/tests/Unit/Exception/InvalidFieldDefinitionExceptionTest.php`:
   - Test the 2 new factories.

3. Create integration-style unit test for boot validation:
   - Translatable field on non-translatable entity type throws.
   - System key (`id`, `uuid`, `langcode`, `default_langcode`, `revision`) marked translatable throws — one assertion per key.
   - Non-system field marked translatable on translatable entity type registers cleanly.

**Files:** ~250 lines of tests across 2-3 files.

## Definition of Done

- [ ] `FieldDefinition::translatable(bool): self` builder added; mirrors `storedIn()`/`indexed()` pattern.
- [ ] 2 new `InvalidFieldDefinitionException` factories.
- [ ] Boot validation throws on (a) translatable field on non-translatable entity type, (b) system key marked translatable.
- [ ] All unit tests pass.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| Boot validation point not obvious; `FieldDefinition` is in L1 (packages/field) but `EntityType::registerFields` is in `packages/entity`. | Place validation in `FieldDefinition::validate(EntityTypeInterface)` (called by `EntityType` boot). Keeps the rule colocated with the field surface. |
| Readonly-builder copy-all-params boilerplate is error-prone. | Compare against `storedIn()` and `indexed()` line by line; ensure every constructor param is copied. Add a phpstan/cs check if needed. |

## Reviewer guidance

- Verify builder copies ALL constructor params (compare to `storedIn()`).
- Verify boot-validation pass runs once per field registration (not per access).
- Verify the SYSTEM_KEYS const matches the entity-key names used in `EntityConstants` (if it exists) — single source of truth.

## Implementation command

```bash
spec-kitty agent action implement WP03 --agent <name>
```

## Activity Log

- 2026-05-12T22:16:01Z – claude:sonnet:waaseyaa-implementer:implementer – shell_pid=528418 – Started implementation via action command
- 2026-05-12T22:21:26Z – claude:sonnet:waaseyaa-implementer:implementer – shell_pid=528418 – Per-field translatable() builder + validation ready
- 2026-05-12T22:22:01Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=532407 – Started review via action command

---
work_package_id: WP02
title: EnumItem plugin
dependencies:
- WP01
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
planning_base_branch: main
merge_target_branch: main
branch_strategy: Plan and merge against main; execution worktree allocated by lanes.json after finalize-tasks. Depends on WP01 — base from WP01's resolved branch.
subtasks:
- T006
- T007
- T008
- T009
- T010
- T011
history:
- timestamp: '2026-04-27T06:43:14Z'
  action: created
  by: /spec-kitty.tasks
authoritative_surface: packages/field/src/Item/
execution_mode: code_change
owned_files:
- packages/field/src/Item/EnumItem.php
- packages/field/src/Item/LabeledCase.php
- packages/field/tests/Unit/Item/EnumItemTest.php
- packages/field/tests/Unit/Item/Fixtures/EnumItemFixtures.php
tags: []
---

# WP02 — EnumItem plugin

**Mission**: `field-type-enum-plugin-01KQ6SJG`
**Branch strategy**: planning + merge target = `main`. Worktree allocated by `lanes.json` after `finalize-tasks`. Base from WP01's resolved branch.

## Objective

Create the `EnumItem` field-type plugin and the optional `LabeledCase` interface. The plugin owns four contracts for backed-enum fields: storage column shape, JSON Schema fragment for `waaseyaa/ai-schema`, runtime validation/coercion, and case-label resolution for admin widgets. Auto-discovered via `#[FieldType(id: 'enum', label: 'Enum')]`.

## Context

- Spec: [../spec.md](../spec.md) (FR-001..FR-008, NFR-001..NFR-003)
- Plan: [../plan.md](../plan.md)
- Research: [../research.md](../research.md) R1, R2, R6
- Data model: [../data-model.md](../data-model.md)
- Contract: [../contracts/enum-item.md](../contracts/enum-item.md)

## Owned files

- `packages/field/src/Item/EnumItem.php` (new)
- `packages/field/src/Item/LabeledCase.php` (new)
- `packages/field/tests/Unit/Item/EnumItemTest.php` (new)
- `packages/field/tests/Unit/Item/Fixtures/EnumItemFixtures.php` (new) — test enums

Do not modify the inferrer, constraint builder, or docs — those land in WP03/WP04/WP05.

## Subtasks

### T006 — `LabeledCase` interface

**Purpose**: Give backed enums a one-method opt-in for custom widget labels.

**Steps**:
1. Create `packages/field/src/Item/LabeledCase.php`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Waaseyaa\Field\Item;

   /**
    * Backed enums implementing this interface provide custom labels for
    * admin form widgets. Without it, EnumItem falls back to the case name.
    */
   interface LabeledCase
   {
       public function getLabel(): string;
   }
   ```
2. If during implementation the `Waaseyaa\Field\Item` namespace turns out to be reserved for `FieldType` plugin classes only, move the interface to `Waaseyaa\Field\LabeledCase` (`packages/field/src/LabeledCase.php`). Update the WP file list in the same commit if so. Document the reason in the commit message.

**Validation**:
- [ ] Class loads via composer autoload.
- [ ] An enum implementing `LabeledCase` returns the custom label per case.

### T007 — `EnumItem` plugin scaffold

**Purpose**: Register the plugin via `#[FieldType]` and inherit from `FieldItemBase`.

**Steps**:
1. Create `packages/field/src/Item/EnumItem.php`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Waaseyaa\Field\Item;

   use Waaseyaa\Field\Attribute\FieldType;
   use Waaseyaa\Field\FieldDefinitionInterface;
   use Waaseyaa\Field\FieldItemBase;

   #[FieldType(id: 'enum', label: 'Enum')]
   final class EnumItem extends FieldItemBase
   {
       // T008–T010 fill this in.
   }
   ```
2. Confirm `AttributeDiscovery` picks it up by clearing the field-type plugin cache and asserting `FieldTypeManager` returns a definition for `'enum'`. (Trace: research R1.)

**Validation**:
- [ ] `FieldTypeManager::getDefinition('enum')` returns a non-null definition.
- [ ] No service-provider wiring is required.

### T008 — Settings validation and error taxonomy

**Purpose**: Reject invalid `enum_class` configuration deterministically with field-and-class-aware messages (NFR-002).

**Steps**:
1. Implement `EnumItem::defaultSettings(): array` returning `['enum_class' => null]`.
2. Add a private helper `assertValidEnumClass(?string $enumClass, string $fieldName): \ReflectionEnum` that:
   - Throws `EnumFieldType.MissingEnumClass` when `$enumClass === null`.
   - Throws `EnumFieldType.UnknownEnumClass` when `class_exists($enumClass) === false`.
   - Constructs a `\ReflectionEnum($enumClass)`. If `isBacked() === false`, throws `EnumFieldType.NotABackedEnum`.
   - If `getBackingType()->getName()` is neither `'string'` nor `'int'`, throws `EnumFieldType.UnsupportedBackingType`.
   - Returns the `ReflectionEnum` for downstream use (memoize in a static array keyed by FQCN to avoid repeated reflection — supports NFR-001).
3. Use a single exception class (`Waaseyaa\Field\Item\EnumFieldTypeException`) with a `code` enum or string discriminator that carries the error-name suffix. (Confirm exception conventions in the framework — if a hierarchy already exists for plugin errors, extend it.)

**Validation**:
- [ ] All four error classes are reachable in unit tests (T011 covers).
- [ ] Error messages include both `$fieldName` and `$enumClass` per NFR-002.
- [ ] Reflection results are cached so repeated calls for the same enum FQCN don't re-reflect.

### T009 — `schemaFor` and `jsonSchemaFor` overrides

**Purpose**: Per-definition column shape and JSON Schema fragment.

**Steps**:
1. Implement:
   ```php
   public static function schemaFor(FieldDefinitionInterface $def): array
   {
       $reflectionEnum = self::reflectionFor($def);
       $backing = $reflectionEnum->getBackingType()->getName();
       return $backing === 'string'
           ? ['value' => ['type' => 'varchar', 'length' => 255]]
           : ['value' => ['type' => 'int']];
   }

   public static function jsonSchemaFor(FieldDefinitionInterface $def): array
   {
       $reflectionEnum = self::reflectionFor($def);
       $backing = $reflectionEnum->getBackingType()->getName();
       $cases = array_map(fn(\BackedEnum $c) => $c->value, $reflectionEnum->getName()::cases());
       return [
           'type' => $backing === 'string' ? 'string' : 'integer',
           'enum' => $cases,
       ];
   }
   ```
   (Both call `assertValidEnumClass` via a `reflectionFor(FieldDefinitionInterface)` helper that pulls `enum_class` and field name from `$def`.)
2. Override the static `schema()` and `jsonSchema()` methods to throw a clear "EnumItem requires per-definition resolution; use schemaFor/jsonSchemaFor" error, so any caller that hits the type-level path discovers the problem loudly rather than silently degrading.

**Validation**:
- [ ] String-backed enum → `varchar(255)` column, `{"type":"string","enum":[...]}` schema.
- [ ] Int-backed enum → `int` column, `{"type":"integer","enum":[...]}` schema.
- [ ] Empty enum → `{"type":..., "enum": []}` (EC-5).

### T010 — Coercion, hydration, and `casesForEnumClass`

**Purpose**: Implement the runtime contract — turn scalars into cases, reject invalid input/stored values, surface labels.

**Steps**:
1. Implement:
   ```php
   public function castToCase(mixed $value, FieldDefinitionInterface $def): \BackedEnum
   ```
   Accepts:
   - A `\BackedEnum` instance whose class === settings.enum_class (return as-is).
   - A scalar matching a declared case backing value (call `$enumClass::tryFrom($value)`; throw `InvalidInputValue` on null).
   - Anything else → `EnumFieldType.InvalidInputValue` mentioning field name + enum class + offending value.

2. Implement:
   ```php
   public function hydrate(mixed $stored, FieldDefinitionInterface $def): \BackedEnum
   ```
   Calls `tryFrom($stored)`; on null throws `EnumFieldType.InvalidStoredValue`. (This covers EC-2: enum was edited after data was written.)

3. Implement:
   ```php
   public static function casesForEnumClass(string $enumClass): array
   ```
   Returns `[<backing_value> => <label>]` ordered by case declaration:
   - Validate `$enumClass` (reuse `assertValidEnumClass` with a synthetic field name `'<enum cases helper>'`).
   - For each case, label resolves via `LabeledCase::getLabel()` if implemented, else `$case->name`.

4. Wire mutation/hydration into `FieldItemBase`'s persistence hooks. Trace which `FieldItemBase` method is called on entity → storage and storage → entity transitions; override there. If those hooks don't yet exist on the base class, surface the gap to reviewers — do **not** silently introduce a new hook in `FieldItemBase` (that belongs in WP01 if it's required).

**Validation**:
- [ ] Round-trip: store an enum case, read back, get the same case.
- [ ] Invalid input: stored `'zzz'` raises `InvalidStoredValue`; passing `'zzz'` to `castToCase` raises `InvalidInputValue`.
- [ ] `casesForEnumClass(LabeledStringEnum::class)` returns custom labels.
- [ ] `casesForEnumClass(StringEnum::class)` (no `LabeledCase` impl) returns case names.

### T011 — Unit tests

**Purpose**: Lock down the NFR-003 surface.

**Steps**:
1. Create `packages/field/tests/Unit/Item/Fixtures/EnumItemFixtures.php` with at least:
   - `StringEnum: string { case A = 'a'; case B = 'b'; }`
   - `IntEnum: int { case Low = 1; case High = 9; }`
   - `LabeledStringEnum: string implements LabeledCase { ... }` with a `getLabel()` impl.
   - `EmptyEnum: string {}` (no cases) for EC-5.
   - `UnitEnum {}` (non-backed) for negative test.
2. Create `packages/field/tests/Unit/Item/EnumItemTest.php` covering:
   - String-backed happy path (`schemaFor`, `jsonSchemaFor`, `castToCase`, `hydrate`).
   - Int-backed happy path.
   - Invalid stored scalar → `InvalidStoredValue`.
   - Invalid input value (non-case scalar; wrong enum-class instance; null when required) → `InvalidInputValue`.
   - Invalid `enum_class` config: missing, unknown class, non-enum class, unit enum.
   - JSON Schema shape (strings, ints, empty enum).
   - `casesForEnumClass`: with and without `LabeledCase`.
3. Use the framework's existing test base if one exists for plugin tests; otherwise extend `\PHPUnit\Framework\TestCase`.

**Validation**:
- [ ] `./vendor/bin/phpunit packages/field/tests/Unit/Item/EnumItemTest.php` is green.
- [ ] All seven NFR-003 surfaces exercised at least once.

## Definition of Done

- [ ] `EnumItem` is auto-discovered by `FieldTypeManager` with id `'enum'`.
- [ ] All settings-validation, schema, and runtime methods implemented per data-model.md and contract.
- [ ] `LabeledCase` interface lives in the field package.
- [ ] `EnumItemTest` covers the seven NFR-003 surfaces.
- [ ] `./vendor/bin/phpunit packages/field/tests/` is green.
- [ ] No file outside `owned_files` is modified.

## Risks

| Risk | Mitigation |
|------|------------|
| Storage layer calls static `schema()` rather than `schemaFor()`, hitting the new "use schemaFor" exception. | T009 makes the failure loud; if it happens, scope expands to plumb `schemaFor` through that storage path. Coordinate with WP01 owner if you find this. |
| `FieldItemBase` lacks a per-instance hook for mutation/hydration coercion. | Stop and surface to reviewers per T010 step 4. Don't add the hook here. |
| Reflection cost on every hydrate. | Cache `ReflectionEnum` per FQCN in a static array on `EnumItem`. |
| `EnumFieldTypeException` taxonomy collides with an existing exception base. | Inspect existing plugin exception conventions before creating; extend if a base exists. |

## Reviewer guidance

- The plugin should be self-contained: no service-provider wiring, no DI mutations.
- Verify the error taxonomy: every error message should include both the field name and the enum FQCN.
- Verify caching: a hot-path test for `castToCase` should not call reflection more than once per enum class.
- Confirm the static `schema()` / `jsonSchema()` deliberate-failure path: it should make a misuse loud, not silent.

## Implementation command

```bash
spec-kitty agent action implement WP02 --agent <name>
```

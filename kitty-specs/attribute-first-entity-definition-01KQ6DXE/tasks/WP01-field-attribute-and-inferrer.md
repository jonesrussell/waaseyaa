---
work_package_id: WP01
title: '#[Field] Attribute and Type Inferrer'
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-011
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-attribute-first-entity-definition-01KQ6DXE
base_commit: e1b86203c340b8432e1347e7f4511281adafdbba
created_at: '2026-04-27T03:49:54.168772+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
shell_pid: "5336"
agent: "claude:opus-4-7:reviewer:reviewer"
history:
- date: '2026-04-27'
  note: Initial generation by /spec-kitty.tasks.
authoritative_surface: packages/entity/src/Attribute/Field
execution_mode: code_change
mission_id: 01KQ6DXEQ01S6PVPT6KF5946TA
mission_slug: attribute-first-entity-definition-01KQ6DXE
owned_files:
- packages/entity/src/Attribute/Field.php
- packages/entity/src/Attribute/FieldTypeInferrer.php
- packages/entity/tests/Unit/Attribute/FieldAttributeTest.php
- packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php
- packages/entity/tests/Fixtures/AttributeFirstEntities/InferrerTestFixtures.php
tags: []
---

# WP01 — `#[Field]` Attribute and Type Inferrer

## Branch Strategy

- **Planning base**: `main`.
- **Merge target**: `main`.
- **Execution worktree**: allocated per lane by `lanes.json` (see `kitty-specs/<mission>/lanes.json` after `finalize-tasks` runs). Run `spec-kitty agent action implement WP01 --agent <name>` to enter the lane workspace.

## Objective

Ship the new `#[Field]` PHP attribute and the pure `FieldTypeInferrer` helper that maps PHP property declarations to field-type IDs. **No** integration with `EntityMetadataReader`, `EntityType`, or production entity classes happens in this WP — that's WP02 and onward. This WP is foundational: the attribute exists and the inferrer's mapping table is unit-tested in isolation.

## Context

- The attribute is the public surface for declaring fields on a content entity property.
- The inferrer is a private helper that codifies the PHP-type → field-type-id table from `data-model.md` and `plan.md`'s AD-4.
- Both classes live under `packages/entity/src/Attribute/`, alongside the existing `ContentEntityType` and `ContentEntityKeys` attributes.
- 16 valid field-type IDs are registered in `waaseyaa/field`: `boolean`, `computed`, `date`, `datetime`, `decimal`, `email`, `entity_reference`, `file`, `float`, `image`, `integer`, `json`, `link`, `list`, `string`, `text`. The inferrer can produce any of these (mostly the scalar ones).
- For backed enums in M1: map to `'string'` with `settings: ['enum_class' => MyEnum::class]`.

Read these before starting:
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/spec.md` (FR-001 through FR-004; §6 Field Templates)
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/plan.md` (AD-4 mapping table)
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/data-model.md` (full type-mapping table with errors)
- `packages/entity/src/Attribute/ContentEntityType.php` (style reference)
- `packages/entity/src/Attribute/ContentEntityKeys.php` (style reference)

---

## Subtask Guidance

### T001 — Create `Waaseyaa\Entity\Attribute\Field` attribute class

**Purpose**: Define the `#[Field]` attribute that downstream code (and apps) place on typed entity properties.

**Steps**:
1. Create `packages/entity/src/Attribute/Field.php`. Mirror the file/header style of `ContentEntityType.php`.
2. Class declaration:
   - `#[\Attribute(\Attribute::TARGET_PROPERTY)]` — non-repeatable.
   - `final readonly class Field`.
   - Namespace `Waaseyaa\Entity\Attribute`.
3. Constructor parameters (all promoted, all public readonly, all with defaults):
   ```php
   public function __construct(
       public ?string $type = null,
       public ?bool $required = null,
       public mixed $default = null,
       public string $label = '',
       public string $description = '',
       public array $settings = [],
       public bool $readOnly = false,
       public bool $translatable = false,
       public bool $revisionable = false,
   ) {}
   ```
4. Add a class-level docblock summarizing the contract: "Marks a public typed property of a content entity class as a persistable field. When `type:` is null, the field type is inferred from the PHP property type by `FieldTypeInferrer`."

**Files**:
- `packages/entity/src/Attribute/Field.php` (new, ~30 lines).

**Validation**:
- [ ] File exists, declares the class, attribute targets `TARGET_PROPERTY`.
- [ ] All constructor parameters are public readonly with sensible defaults.
- [ ] No business logic in this class — it's a data-only attribute.

---

### T002 — Create `Waaseyaa\Entity\Attribute\FieldTypeInferrer` helper

**Purpose**: Pure helper that converts a `\ReflectionProperty` + the `Field` attribute instance found on it into a `{type, required, settings}` triple ready for `FieldDefinition` construction.

**Steps**:
1. Create `packages/entity/src/Attribute/FieldTypeInferrer.php`.
2. Single static method:
   ```php
   final class FieldTypeInferrer
   {
       /**
        * @return array{type: string, required: bool, settings: array<string, mixed>}
        */
       public static function infer(\ReflectionProperty $property, Field $attribute): array;
   }
   ```
3. Logic outline (apply in order):
   - Read `$property->getType()` → `$reflectionType`.
   - Determine `bool $isNullable = $reflectionType?->allowsNull() ?? false`.
   - Determine `string|null $phpTypeName`:
     - If `ReflectionNamedType` → `$reflectionType->getName()`.
     - If `ReflectionUnionType` or `ReflectionIntersectionType` → `null` (forces explicit `type:`).
     - If null (no declaration) → `null`.
   - If `$attribute->type !== null`:
     - Validate the explicit type id is in the known set (boolean, computed, date, datetime, decimal, email, entity_reference, file, float, image, integer, json, link, list, string, text). If not, throw `EntityMetadataException` with the unknown-id message.
     - If `$phpTypeName` is also set, optionally validate compatibility (see error rules in data-model.md). For T002 keep this strict: throw on mismatch.
     - Return `['type' => $attribute->type, 'required' => $attribute->required ?? !$isNullable, 'settings' => $attribute->settings]`.
   - Else (`$attribute->type === null`), apply the mapping table:
     - `string` → `'string'`
     - `int` → `'integer'`
     - `bool` → `'boolean'`
     - `float` → `'float'`
     - `array` → `'json'`
     - `\DateTimeImmutable` → `'datetime'`
     - Backed-enum class (use `is_subclass_of($phpTypeName, \BackedEnum::class)`) → `'string'`, settings include `'enum_class' => $phpTypeName`.
     - Else → throw `EntityMetadataException` with the "cannot infer" message.
   - `required` defaults: when `$attribute->required !== null`, use that; otherwise `!$isNullable`.
   - `settings` defaults: merge `$attribute->settings` over inferred settings (e.g. enum_class) — explicit user settings win.
4. Throw `\Waaseyaa\Entity\Exception\EntityMetadataException` (existing class) on errors. Include class FQN, property name, and a remediation hint (NFR-004).

**Files**:
- `packages/entity/src/Attribute/FieldTypeInferrer.php` (new, ~120 lines including method-level documentation).

**Validation**:
- [ ] All mapping rows from data-model.md §"Type Inference" are covered.
- [ ] Error messages identify class, property, and (where applicable) the offending PHP type.
- [ ] No global state; the helper is pure (same input → same output).

**Edge cases / error semantics** (asserted by tests in T005):
- Untyped property + null `type:` → throw with hint "declare a property type or pass type: explicitly".
- Union type + null `type:` → same.
- Intersection type + null `type:` → same.
- Unknown explicit `type: 'unicorn'` → throw with the list of valid ids.
- Conflict (explicit `type: 'integer'` on `public string $x`) → throw identifying both types.
- Enum with explicit `type: 'string'` (the M1 fallback if user wants to be explicit) → accepted, but settings must still pick up `enum_class`.

---

### T003 — Unit tests for `Field` attribute

**Purpose**: Lock the attribute's public surface.

**Steps**:
1. Create `packages/entity/tests/Unit/Attribute/FieldAttributeTest.php`.
2. Test cases (each ≤ 10 lines):
   - `new Field()` constructs with all-default parameter values.
   - `new Field(type: 'string', required: true, default: 'foo', label: 'Name', description: 'desc', settings: ['x' => 1])` exposes those values via the public readonly properties.
   - Reflection: `(new \ReflectionClass(Field::class))->getAttributes(\Attribute::class)` confirms `\Attribute::TARGET_PROPERTY`.
   - Apply `#[Field]` to a public property of a fixture class; reading via reflection returns a `Field` instance.

**Files**:
- `packages/entity/tests/Unit/Attribute/FieldAttributeTest.php` (new, ~80 lines).

**Validation**:
- [ ] `vendor/bin/phpunit packages/entity/tests/Unit/Attribute/FieldAttributeTest.php` is green.
- [ ] All tests are isolated; no DB / kernel boot.

---

### T004 — Unit tests for `FieldTypeInferrer` (full mapping table)

**Purpose**: Lock every row of the inference table.

**Steps**:
1. Create `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php`.
2. Create a fixtures file `packages/entity/tests/Fixtures/AttributeFirstEntities/InferrerTestFixtures.php` containing a single class with one public typed property per inferrence rule:
   ```php
   final class InferrerTestFixtures {
       public string $aString;
       public ?string $aNullableString;
       public int $anInt;
       public ?int $aNullableInt;
       public bool $aBool;
       public ?bool $aNullableBool;
       public float $aFloat;
       public ?float $aNullableFloat;
       public array $anArray;
       public ?array $aNullableArray;
       public \DateTimeImmutable $aDateTime;
       public ?\DateTimeImmutable $aNullableDateTime;
       public InferrerSampleEnum $anEnum;        // backed enum
       public ?InferrerSampleEnum $aNullableEnum;
       public string|int $aUnion;                // for error tests
       public $untyped;                          // for error tests
   }
   enum InferrerSampleEnum: string {
       case Foo = 'foo';
       case Bar = 'bar';
   }
   ```
3. Test method per inference row using a data provider:
   ```php
   #[DataProvider('inferenceCases')]
   public function testInfer(string $propertyName, ?string $explicitType, array $expected): void { ... }

   public static function inferenceCases(): array { ... }
   ```
   Each row asserts the resulting `[type, required, settings]` triple.
4. Cover the explicit-`type:`-overrides-PHP-type cases too (e.g. `string` property with `type: 'text'`).

**Files**:
- `packages/entity/tests/Fixtures/AttributeFirstEntities/InferrerTestFixtures.php` (new, ~30 lines).
- `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` (new, ~250 lines).

**Validation**:
- [ ] Every row in data-model.md "Type Inference" table is asserted.
- [ ] Backed-enum case asserts `settings: ['enum_class' => InferrerSampleEnum::class]`.
- [ ] Tests run < 1 s total.

---

### T005 — Error-path tests

**Purpose**: Lock each error message and ensure NFR-004 (offending class FQN + property + hint) holds.

**Steps**:
1. Add to `FieldTypeInferrerTest.php` a section "errors":
   - Untyped property with `type:` null → expect `EntityMetadataException` matching `/cannot infer field type/i` and containing the property name.
   - Union type without explicit `type:` → similar.
   - Intersection type without explicit `type:` → similar.
   - Unknown explicit `type: 'unicorn'` → expect message containing the valid-IDs list.
   - Conflict: `public string $x` with `Field(type: 'integer')` → expect message naming both `string` and `integer`.
   - Class is not a subclass of `BackedEnum` and PHP type unknown → expect "cannot infer" message.
2. For each error case, also assert the exception message includes:
   - The fixture class FQN.
   - The property name.
   - A remediation hint per data-model.md §Errors table.

**Files**:
- `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php` (extended).

**Validation**:
- [ ] Each error path raises `EntityMetadataException`.
- [ ] Each error message matches the data-model.md template.
- [ ] No raw `\Throwable` or generic `\InvalidArgumentException` leaks.

---

## Definition of Done

- All five subtask checkboxes ticked.
- `vendor/bin/phpunit packages/entity/tests/Unit/Attribute/` green for the new test files.
- `vendor/bin/phpstan analyse packages/entity/src/Attribute/Field.php packages/entity/src/Attribute/FieldTypeInferrer.php` clean (or baseline updated minimally).
- No file outside `owned_files` has been modified.

## Risks

- Reflection-on-enum subclass detection: `is_subclass_of($name, \BackedEnum::class)` requires the type name to be a real class string. Confirm with a fixture enum.
- `?array` is a tricky case — make sure the fixture distinguishes `array` (required `json`) vs `?array` (optional `json`).
- Don't try to handle generic `array<T>` shapes — PHPDoc-only generics are out of M1 scope.

## Reviewer guidance

- Verify the inference table in code matches data-model.md row-for-row.
- Verify error messages provide actionable hints (NFR-004).
- Confirm `Field.php` carries no logic — pure attribute.
- Confirm `FieldTypeInferrer.php` is stateless / pure.

## Implementation command

```
spec-kitty agent action implement WP01 --agent <name>
```

## Activity Log

- 2026-04-27T03:49:56Z – claude:opus-4-7:implementer:implementer – shell_pid=27768 – Assigned agent via action command
- 2026-04-27T03:55:08Z – claude:opus-4-7:implementer:implementer – shell_pid=27768 – Ready for review
- 2026-04-27T03:55:48Z – claude:opus-4-7:reviewer:reviewer – shell_pid=5336 – Started review via action command
- 2026-04-27T03:57:07Z – claude:opus-4-7:reviewer:reviewer – shell_pid=5336 – Review passed: Field attribute is pure data-only final readonly TARGET_PROPERTY, FieldTypeInferrer is stateless with full data-model.md mapping coverage, error messages carry FQN+property+hint per NFR-004, all 31 tests green, owned-files boundary respected. Compatibility groups (text-like/integer-list/float-decimal/datetime-date) are a defensible reading of the spec's 'compatible' language and necessary to satisfy T004's explicit string->text override case.

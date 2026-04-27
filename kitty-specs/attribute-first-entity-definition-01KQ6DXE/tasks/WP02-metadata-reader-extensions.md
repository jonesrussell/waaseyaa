---
work_package_id: WP02
title: Metadata Reader Extensions
dependencies:
- WP01
requirement_refs:
- FR-006
- FR-012
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T006
- T007
- T008
- T009
- T010
agent: "claude:opus-4-7:implementer:implementer"
shell_pid: "27068"
history:
- date: '2026-04-27'
  note: Initial generation by /spec-kitty.tasks.
authoritative_surface: packages/entity/src/Attribute
execution_mode: code_change
mission_id: 01KQ6DXEQ01S6PVPT6KF5946TA
mission_slug: attribute-first-entity-definition-01KQ6DXE
owned_files:
- packages/entity/src/Attribute/ContentEntityType.php
- packages/entity/src/Attribute/EntityClassMetadata.php
- packages/entity/src/Attribute/EntityMetadataReader.php
- packages/entity/tests/Unit/Attribute/ContentEntityTypeTest.php
- packages/entity/tests/Unit/Attribute/EntityClassMetadataTest.php
- packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php
- packages/entity/tests/Fixtures/AttributeFirstEntities/MetadataTestFixtures.php
tags: []
---

# WP02 — Metadata Reader Extensions

## Branch Strategy

- **Planning base**: `main`. **Merge target**: `main`. Worktree per lane.

## Objective

Extend the existing attribute-reading infrastructure (`#[ContentEntityType]`, `EntityClassMetadata`, `EntityMetadataReader`) to surface `label`/`description` and to resolve `#[Field]`-decorated properties into a `FieldDefinition` map. After this WP, calling `EntityMetadataReader::forClass(MyEntity::class)` returns a fully-populated metadata record including the field map. **No** changes to `EntityType` or to the public registration path — that's WP03.

## Context

- `EntityMetadataReader` already caches per-class metadata via a static array.
- The reader currently produces an `EntityClassMetadata` record carrying `typeId` + `keys`. We extend that record to also carry `label`, `description`, `fields`.
- The `FieldDefinition` constructor (in `packages/field/src/FieldDefinition.php`) is the target type. Construct via named arguments, populating from the `FieldTypeInferrer` output plus the original `#[Field]` attribute parameters.

Read these before starting:
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/spec.md` (FR-005, FR-006, FR-012)
- `kitty-specs/attribute-first-entity-definition-01KQ6DXE/data-model.md` (resolved metadata structure)
- `packages/entity/src/Attribute/EntityMetadataReader.php` (existing implementation — `resolveTypeId`, `resolveKeys` patterns)
- `packages/entity/src/Attribute/EntityClassMetadata.php` (existing record)
- `packages/field/src/FieldDefinition.php` (target shape — constructor signature)

---

## Subtask Guidance

### T006 — Extend `#[ContentEntityType]` with `label` and `description`

**Purpose**: Add the new metadata-carrying parameters to the class-level attribute.

**Steps**:
1. Open `packages/entity/src/Attribute/ContentEntityType.php`.
2. Add `label: string = ''` and `description: string = ''` parameters to the constructor (promoted, public readonly).
3. Update the docblock to mention the two new parameters and their semantics: empty `label` falls back to the type ID at consumption time; `description` is optional human prose.
4. Existing call sites in production code (`#[ContentEntityType(id: 'foo')]`) continue to work — the new parameters have defaults.

**Files**:
- `packages/entity/src/Attribute/ContentEntityType.php` (modified, ~5 line delta).

**Validation**:
- [ ] Class still `final readonly`.
- [ ] Existing single-arg call sites compile.
- [ ] New params accept empty defaults.

---

### T007 — Extend `EntityClassMetadata` to carry `label`, `description`, `fields`

**Purpose**: The resolved-metadata record holds everything `EntityType::fromClass()` will need (in WP03).

**Steps**:
1. Open `packages/entity/src/Attribute/EntityClassMetadata.php`.
2. Add three new constructor parameters (promoted, public readonly):
   ```php
   public string $label = '',
   public string $description = '',
   /** @var array<string, FieldDefinition> */
   public array $fields = [],
   ```
3. Add `use Waaseyaa\Field\FieldDefinition;` if not already imported.
4. Keep the existing `typeId` + `keys` parameters.

**Files**:
- `packages/entity/src/Attribute/EntityClassMetadata.php` (modified, ~5 line delta + import).

**Validation**:
- [ ] Existing constructions of `EntityClassMetadata(...)` still compile (defaults absorb the new params).
- [ ] Type annotation `array<string, FieldDefinition>` is accurate.

---

### T008 — Update `EntityMetadataReader::forClass()` to populate label/description

**Purpose**: When reading a class's metadata, surface the label/description from `#[ContentEntityType]` into the cached record.

**Steps**:
1. Open `packages/entity/src/Attribute/EntityMetadataReader.php`.
2. Add a private `resolveLabelAndDescription(string $class): array{label: string, description: string}` method (mirrors `resolveTypeId` style — walks the class to find `#[ContentEntityType]` and returns the label/description).
3. Update `forClass()` to call `resolveLabelAndDescription()` and pass results into `EntityClassMetadata` constructor.
4. Do **not** populate `$fields` here — that's T009. Leave `fields: []` for now; T009 will wire it.

**Files**:
- `packages/entity/src/Attribute/EntityMetadataReader.php` (modified).

**Validation**:
- [ ] `forClass(SomeEntity::class)->label === 'expected label'` for a fixture entity.
- [ ] `forClass(SomeEntityWithoutLabel::class)->label === ''` (default).

---

### T009 — Add `EntityMetadataReader::resolveFields()` with hierarchy walk

**Purpose**: The marquee method. Walks the class hierarchy from `ContentEntityBase` down, reading `#[Field]` attributes on public typed properties, building `FieldDefinition` instances.

**Steps**:
1. Add public static method:
   ```php
   /**
    * @param class-string $class
    * @return array<string, FieldDefinition>
    */
   public static function resolveFields(string $class): array;
   ```
2. Implementation outline:
   - If not subclass of `ContentEntityBase`, return `[]`.
   - Walk hierarchy bottom-up (collect parents from `ContentEntityBase` boundary down to `$class`), then iterate top-down so child classes can override.
   - For each class in the chain, iterate `(new \ReflectionClass($cls))->getProperties(\ReflectionProperty::IS_PUBLIC)`.
   - For each property, look for `#[Field]` attribute via `getAttributes(Field::class)`.
   - For each `#[Field]` found:
     - Read the attribute instance: `$field = $attr->newInstance()`.
     - Call `FieldTypeInferrer::infer($property, $field)` to get `[type, required, settings]`.
     - Construct `FieldDefinition` via named args:
       ```php
       new FieldDefinition(
           name: $property->getName(),
           type: $inferred['type'],
           cardinality: 1,
           settings: $inferred['settings'],
           defaultValue: $field->default,
           label: $field->label,
           description: $field->description,
           required: $inferred['required'],
           readOnly: $field->readOnly,
           translatable: $field->translatable,
           revisionable: $field->revisionable,
       );
       ```
     - Map by `$property->getName()` so child class declarations override parent.
3. Update `forClass()` to call `resolveFields()` and pass results into `EntityClassMetadata`.
4. Update `clearCacheForClass()` and `clearCache()` — they already clear the static cache; ensure the field map is part of the cached record (it is, since we store the whole `EntityClassMetadata`).

**Files**:
- `packages/entity/src/Attribute/EntityMetadataReader.php` (extended; +~80 lines).

**Validation**:
- [ ] `EntityMetadataReader::resolveFields(MetadataTestFixture::class)` returns the expected map.
- [ ] Inheritance: a subclass's `#[Field]` overrides parent class's same-named field.
- [ ] Cache: second call to `forClass()` is sub-0.1ms (covered in WP03 NFR test, but smoke-check here).
- [ ] Throws on bad attribute usage (delegated to `FieldTypeInferrer`).

---

### T010 — Tests for the above

**Purpose**: Lock the new metadata-resolution surface.

**Steps**:
1. Create `packages/entity/tests/Fixtures/AttributeFirstEntities/MetadataTestFixtures.php` with:
   - One simple entity class: `#[ContentEntityType(id: 'simple', label: 'Simple', description: 'Test entity')]` + 4 `#[Field]` properties (mix of types).
   - One inheritance pair: parent + child where child overrides one parent field.
   - One entity class without a label (default behavior).
   - One entity class with `#[Field(type: 'text')]` overriding inferred `'string'`.
2. Create test files:
   - `packages/entity/tests/Unit/Attribute/ContentEntityTypeTest.php` — verify the new `label` and `description` parameters round-trip via attribute reflection.
   - `packages/entity/tests/Unit/Attribute/EntityClassMetadataTest.php` — verify the new fields slot. Mostly direct construction.
   - `packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php` — extend (or create) to cover:
     - `forClass()` returns label/description.
     - `resolveFields()` returns the expected field map.
     - Inheritance: child override wins.
     - Non-`ContentEntityBase` class returns empty fields.
     - Cache hit on second call (use a counter / spy or just measure call count via reflection-cache state).
3. Use the fixtures from T004's `InferrerTestFixtures.php` indirectly only — T010's fixtures are richer and class-level (carry `#[ContentEntityType]`).

**Files**:
- `packages/entity/tests/Fixtures/AttributeFirstEntities/MetadataTestFixtures.php` (new, ~70 lines).
- `packages/entity/tests/Unit/Attribute/ContentEntityTypeTest.php` (new, ~50 lines).
- `packages/entity/tests/Unit/Attribute/EntityClassMetadataTest.php` (new, ~40 lines).
- `packages/entity/tests/Unit/Attribute/EntityMetadataReaderTest.php` (new or extended, ~150 lines).

**Validation**:
- [ ] All new tests green via `vendor/bin/phpunit packages/entity/tests/Unit/Attribute/`.
- [ ] PHPStan passes against the modified files.
- [ ] No test reaches outside `packages/entity/`.

---

## Definition of Done

- All five subtask checkboxes ticked.
- `vendor/bin/phpunit packages/entity/tests/Unit/Attribute/` green.
- `EntityMetadataReader::forClass($x)->fields` returns the expected `FieldDefinition[]` for fixture entities.
- No file outside `owned_files` modified.

## Risks

- **Inheritance order**: read carefully — the existing `resolveKeys()` reverses the chain so child classes win. Mirror that exactly.
- **`FieldDefinition` constructor changes**: don't change `FieldDefinition`'s shape. Build instances using its existing named-arg constructor.
- **`array<string, FieldDefinition>` ordering**: tests might assume an order. The walk produces declaration order per class, child-after-parent. Document and stick to it.

## Reviewer guidance

- Verify all label/description data flows from attribute → `EntityClassMetadata` correctly.
- Verify `resolveFields()` walks hierarchy in the same direction as `resolveKeys()`.
- Confirm `FieldDefinition` instances are constructed with all relevant fields populated (label, description, settings, required, default).

## Implementation command

```
spec-kitty agent action implement WP02 --agent <name>
```

## Activity Log

- 2026-04-27T03:57:47Z – claude:opus-4-7:implementer:implementer – shell_pid=27068 – Started implementation via action command

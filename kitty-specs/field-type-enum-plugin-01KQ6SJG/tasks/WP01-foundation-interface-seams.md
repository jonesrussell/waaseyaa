---
work_package_id: WP01
title: Foundation interface seams
dependencies: []
requirement_refs:
- FR-003
- FR-006
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-field-type-enum-plugin-01KQ6SJG
base_commit: 830f4b75c87b390113c533499a4bf60b66a54b2d
created_at: '2026-04-27T06:50:49.659059+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
shell_pid: '36224'
history:
- timestamp: '2026-04-27T06:43:14Z'
  action: created
  by: /spec-kitty.tasks
authoritative_surface: packages/field/src/
execution_mode: code_change
owned_files:
- packages/field/src/FieldTypeInterface.php
- packages/field/src/FieldItemBase.php
- packages/field/src/FieldDefinition.php
- packages/field/src/FieldTypeManager.php
- packages/field/tests/Unit/FieldDefinitionJsonSchemaRegressionTest.php
tags: []
---

# WP01 — Foundation interface seams

**Mission**: `field-type-enum-plugin-01KQ6SJG`
**Branch strategy**: planning + merge target = `main`. The worktree for this WP is allocated by `lanes.json` after `finalize-tasks`. Run `spec-kitty agent action implement WP01 --agent <name>` from the repo root once the lane is computed.

## Objective

Add the per-definition seams (`jsonSchemaFor`, `schemaFor`) to the field-type plugin contract and route `FieldDefinition::toJsonSchema()` through `FieldTypeManager`. Default implementations on `FieldItemBase` preserve every existing field type's behavior. After this WP, the plugin layer has a clean place for `EnumItem` (WP02) to plug in its per-field schema and per-field column shape — without touching any existing field-type plugin's behavior.

## Context

- Spec: [../spec.md](../spec.md) (FR-006, FR-003, NFR-001)
- Plan: [../plan.md](../plan.md) §"Decisions Recorded"
- Research: [../research.md](../research.md) R5, R6
- Contract: [../contracts/field-type-interface.md](../contracts/field-type-interface.md)

Today, `FieldDefinition::toJsonSchema()` (`packages/field/src/FieldDefinition.php:88-120`) is a hardcoded `match` over the field-type id. It does NOT delegate to the field-type plugin's `jsonSchema()` method. Storage column shapes come from static `FieldTypeInterface::schema()` which cannot read settings. The enum plugin (WP02) needs both `jsonSchema` and `schema` to vary by field definition (specifically by `settings.enum_class`); to enable that without breaking existing types, this WP adds *per-definition* variants alongside the existing static methods.

## Owned files

- `packages/field/src/FieldTypeInterface.php` — add interface methods
- `packages/field/src/FieldItemBase.php` — add default impls
- `packages/field/src/FieldDefinition.php` — refactor `toJsonSchema()` to delegate
- `packages/field/src/FieldTypeManager.php` — add helper methods
- `packages/field/tests/Unit/FieldDefinitionJsonSchemaRegressionTest.php` — new regression test

Do not modify any other files in this WP. If you discover the storage layer or another caller needs threading, stop and flag — do not silently expand scope.

## Subtasks

### T001 — Extend `FieldTypeInterface`

**Purpose**: Add per-definition seams to the plugin contract.

**Steps**:
1. Open `packages/field/src/FieldTypeInterface.php`.
2. Add (alongside the existing `schema()` and `jsonSchema()` static methods):
   ```php
   public static function jsonSchemaFor(FieldDefinitionInterface $def): array;
   public static function schemaFor(FieldDefinitionInterface $def): array;
   ```
3. Add the necessary `use` for `FieldDefinitionInterface` if absent.

**Validation**:
- [ ] Interface compiles (`composer dump-autoload` succeeds).
- [ ] No existing class accidentally satisfies these new methods via inheritance (they will after T002).

### T002 — Default implementations on `FieldItemBase`

**Purpose**: Preserve behavior for every existing field type without forcing them to override.

**Steps**:
1. Open `packages/field/src/FieldItemBase.php`.
2. Add:
   ```php
   public static function jsonSchemaFor(FieldDefinitionInterface $def): array
   {
       return static::jsonSchema();
   }

   public static function schemaFor(FieldDefinitionInterface $def): array
   {
       return static::schema();
   }
   ```
3. Add the import for `FieldDefinitionInterface` at the top of the file.

**Validation**:
- [ ] `StringItem`, `IntegerItem`, `BooleanItem`, `FloatItem` (if present), `TextItem` (if present), `EntityReferenceItem` (if present) all still satisfy the interface without modification.
- [ ] No method override is required on any existing item class.

### T003 — `FieldTypeManager` helpers

**Purpose**: Provide a single resolution point that callers (incl. `FieldDefinition`) use.

**Steps**:
1. Open `packages/field/src/FieldTypeManager.php`.
2. Add:
   ```php
   public function jsonSchemaFor(FieldDefinitionInterface $def): array
   {
       $itemClass = $this->resolveItemClass($def->getType());
       return $itemClass::jsonSchemaFor($def);
   }

   public function schemaFor(FieldDefinitionInterface $def): array
   {
       $itemClass = $this->resolveItemClass($def->getType());
       return $itemClass::schemaFor($def);
   }
   ```
3. Use the existing internal lookup that maps `id => fully-qualified item class`. The current `FieldTypeManager` extends/delegates to `DefaultPluginManager` (per research R1); inspect existing methods (e.g. `getDefinition($id)`) to find the right way to resolve to the item class. Name the helper `resolveItemClass` if not already present, but do **not** add a public new selector — keep it private.

**Validation**:
- [ ] Calling `$mgr->jsonSchemaFor($string_field_def)` returns the same array as `StringItem::jsonSchema()`.
- [ ] Calling `$mgr->schemaFor($int_field_def)` returns the same array as `IntegerItem::schema()`.

### T004 — Refactor `FieldDefinition::toJsonSchema()`

**Purpose**: Make `FieldDefinition::toJsonSchema()` delegate, so `EnumItem` can answer for itself in WP02.

**Steps**:
1. Open `packages/field/src/FieldDefinition.php`.
2. Replace the hardcoded `match` body of `toJsonSchema()` (lines 88–120) with:
   ```php
   public function toJsonSchema(): array
   {
       return $this->fieldTypeManager->jsonSchemaFor($this);
   }
   ```
3. Thread a `FieldTypeManager` reference into `FieldDefinition`. Likely paths:
   - Constructor-inject if construction is centralised.
   - If `FieldDefinition` is a value object created in many places (e.g. directly from arrays), prefer adding a setter or factory method on `FieldTypeManager` (`fromArray(array $def): FieldDefinition`) and migrating the call sites in this WP. Choose the smallest approach that compiles.
4. If a wide refactor is required to thread the manager, **stop and document** in the WP review notes; the alternative is a service-locator-ish access pattern that should be discussed before proceeding.

**Validation**:
- [ ] All existing tests under `packages/field/tests/` pass.
- [ ] `FieldDefinition::toJsonSchema()` returns the same array as the prior hardcoded `match` for every existing field-type id (verified by T005).

### T005 — Regression test for behavior preservation

**Purpose**: Lock down that the delegation doesn't change observable output for any existing field type.

**Steps**:
1. Create `packages/field/tests/Unit/FieldDefinitionJsonSchemaRegressionTest.php`.
2. For each existing field-type id (`'string'`, `'integer'`, `'boolean'`, `'float'`, `'text'`, `'entity_reference'` — confirm the actual list by reading the pre-refactor `match` in git history or in the diff), assert that `FieldDefinition::toJsonSchema()` returns the schema previously produced. Capture the expected array as a literal; do not call `match` from the test.
3. The test should be readable as a contract: "after this WP, these field types still emit these schemas."

**Validation**:
- [ ] Test passes after T004 lands.
- [ ] If any single field type's output differs, treat it as a bug in T004 — fix the delegation rather than relaxing the test.

## Definition of Done

- [ ] `FieldTypeInterface` has `jsonSchemaFor()` and `schemaFor()`.
- [ ] `FieldItemBase` provides defaults that preserve current behavior.
- [ ] `FieldTypeManager` has matching public helpers.
- [ ] `FieldDefinition::toJsonSchema()` delegates through the manager.
- [ ] Regression test asserts bit-identical output for every existing field-type id.
- [ ] `./vendor/bin/phpunit packages/field/tests/` is green.
- [ ] No file outside `owned_files` is modified.

## Risks

| Risk | Mitigation |
|------|------------|
| Storage layer calls static `schema()` rather than going through `schemaFor()`. | Out of scope here — WP02 will ensure `EnumItem::schema()` falls back gracefully (e.g. throws "use schemaFor"). If WP02 surfaces an actual blocker, expand WP01's scope at that point, not preemptively. |
| `FieldDefinition` is constructed in many places without a `FieldTypeManager`. | Prefer factory methods on `FieldTypeManager` over a wide constructor change; if both feel wrong, stop and ask. |
| Direct implementers of `FieldTypeInterface` outside `FieldItemBase` exist. | Search via `grep -rn "implements FieldTypeInterface" packages/`. If any are found and don't extend `FieldItemBase`, add the same default impls there or have them extend `FieldItemBase`. |

## Reviewer guidance

- Diff should be additive on `FieldTypeInterface` and `FieldItemBase`, with one method-body change in `FieldDefinition::toJsonSchema()` and a new helper in `FieldTypeManager`.
- The regression test is the safety net — if it fails for any existing type, the delegation has a defect.
- Keep an eye on the `FieldDefinition` construction path; the change should not require a sprawling refactor across the entity package.

## Implementation command

```bash
spec-kitty agent action implement WP01 --agent <name>
```

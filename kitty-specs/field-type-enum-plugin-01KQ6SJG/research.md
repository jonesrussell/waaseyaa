# Research: Enum Field-Type Plugin

**Mission**: `field-type-enum-plugin-01KQ6SJG`
**Date**: 2026-04-27

This document resolves the open questions from the spec (Assumptions 1–3, FR-006 schema seam, FR-009 inferrer surface, FR-010/FR-012 consumer surface) with concrete file:line citations against the waaseyaa-framework monorepo at `C:\Users\jones\Projects\Rainbow\waaseyaa-framework`.

---

## R1 — `#[FieldType]` discovery and plugin registration

**Decision**: `EnumItem` participates in the existing discovery mechanism with **zero registration changes** beyond placing the file at `packages/field/src/Item/EnumItem.php` with a `#[FieldType(id: 'enum', label: 'Enum')]` attribute and extending `FieldItemBase`.

**Rationale**:
- `packages/field/src/Attribute/FieldType.php:10` — attribute targets classes; carries `id`, `label`, `description`, `package`, `category`, default cardinality/widget/formatter.
- `packages/plugin/src/Discovery/AttributeDiscovery.php:20-31` — `getDefinitions()` recursively scans configured directories for PHP files, instantiates `#[FieldType]` attributes, builds `PluginDefinition` objects keyed by `id`.
- `packages/field/src/FieldTypeManager.php:12-30` — constructor accepts `directories[]`, builds `AttributeDiscovery` for `FieldType::class`, caches under `'field_type_definitions'`.
- Existing precedent: `StringItem` (`packages/field/src/Item/StringItem.php:10-16`), `IntegerItem` (`packages/field/src/Item/IntegerItem.php:10-16`), `BooleanItem` (`packages/field/src/Item/BooleanItem.php:10-16`) — all live in the same directory with no service-provider wiring.

**Plugin contract** (from `FieldTypeInterface`, `packages/field/src/FieldTypeInterface.php:9-20` and base `FieldItemBase`, `packages/field/src/FieldItemBase.php:13`):

| Method | Purpose | EnumItem behavior |
|--------|---------|-------------------|
| `static schema(): array` | Storage column shape | Returns `['value' => ['type' => 'varchar'\|'int', ...]]` based on backing type — but see R6. |
| `static defaultSettings(): array` | Default settings | Returns `['enum_class' => null]` (required setting; `null` triggers config error). |
| `static defaultValue(): mixed` | Default value | `null`. |
| `static jsonSchema(): array` | JSON Schema fragment | Static method; cannot see settings. We will add an instance/parameterized variant — see R5. |

**Alternatives considered**:
- Manifest-based registration: rejected; framework convention is attribute scan.
- Service-provider explicit wiring: rejected; existing items don't do this.

---

## R2 — Widget label-provider seam

**Decision**: No widget-specific label seam exists today on field-type plugins. Add a **static helper method on `EnumItem`** that returns the case→label map, callable from constraint builder, admin widgets, and the JSON Schema emitter:

```php
public static function casesForEnumClass(string $enumClass): array {
    // Returns ['<backing_value>' => '<label>', ...] in case declaration order.
    // Label resolution: if $enumClass implements a label-provider seam (e.g. a
    //   getLabel(): string method on each case via an interface), use it.
    //   Otherwise fall back to the case name.
}
```

**Rationale**:
- Search across `packages/field/src/Item/` found no `getOptions`, `getAllowedValues`, or `optionsProvider` methods on existing items.
- The closest existing read of `enum_class` is `packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php:67-78`, which calls `BackedEnum::cases()` directly to construct a `Choice` constraint. This is a duplicated pattern that the plugin should own.
- Putting the helper on `EnumItem` (rather than introducing a new `OptionsProviderInterface`) is the smallest viable seam. Future field types that need similar option lists can either import this helper or be refactored when a second use case appears (YAGNI on a generic interface).

**Label-resolution policy** (used by the helper):
1. If `$enumClass` implements a framework-provided `LabeledCase` interface (to be confirmed during implementation; if absent, define a one-method interface — `getLabel(): string` — in `packages/field/src/Item/`), call it per case.
2. Otherwise: fall back to the case `name` (PHP backed enum's declared name, e.g. `Status::ACTIVE` → `"ACTIVE"`).
3. The helper does NOT do further humanization (title-casing, translation). Those concerns belong to widgets/translators downstream.

**Alternatives considered**:
- A new `OptionsProviderInterface` injected via DI: rejected; over-engineered for one consumer.
- Sourcing labels from PHP doc-comments via reflection: rejected; brittle.

---

## R3 — Existing consumer migration surface (FR-010, FR-012)

**Decision**: The migration surface in this monorepo is narrower than the spec assumed. Concrete migrations:

| Site | File:Line | What changes |
|------|-----------|--------------|
| Inferrer emission | `packages/entity/src/Attribute/FieldTypeInferrer.php:144-148` | Replace `return 'string'` with `return 'enum'`; choose `string` vs `int` is no longer the inferrer's job (the plugin owns it via settings). Also update `VALID_TYPE_IDS` (lines 27–44) to include `'enum'`. |
| Inferrer tests | `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php:78-85`, `139-148`, `150` | Update assertions from `'string' + enum_class` to `'enum' + enum_class`. |
| Constraint builder | `packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php:67-78` | Today reads `enum_class` from settings regardless of field type and calls `BackedEnum::cases()`. Refactor to delegate to `EnumItem`'s validation path when field type is `'enum'`. The legacy branch that reads `enum_class` from a `'string'` field is removed. |
| `Field` attribute docstring | `packages/entity/src/Attribute/Field.php` (comment) | Update example to show `enum` field type. |
| `packages/entity/tests/Unit/Validation/Fixture/BackedEnumCastEntity.php:13-21` | (verify during implementation) | Uses cast, not field-type definition — likely **not affected**, but plan WP must verify. |
| `packages/entity/tests/Fixtures/AttributeFirstEntities/InferrerTestFixtures.php:45-46` | Test fixture | Used by inferrer tests — assertions update only, fixture itself stays. |

**Production entity classes**: NOT FOUND. The grep across `src/Entity/` and `packages/*/src/Entity/` returned **zero** entity classes currently declaring `'string' + enum_class` (or integer variant) directly. The bridge is exercised only via `FieldTypeInferrer` from attribute-first declarations, and there are no production entity classes carrying backed-enum fields yet — only test fixtures.

**Implication for the spec**: FR-010 collapses to "migrate inferrer + constraint builder + tests" rather than a multi-entity sweep. AS-7 grep verification still applies but is expected to find zero remaining production hits after the changes above.

**Alternatives considered**:
- Leaving constraint builder unchanged with both legacy and new paths: rejected per C-004 (hard cutover).

---

## R4 — `FieldTypeInferrer` refactor target

**Decision**: Refactor at one site, plus the type-id whitelist.

**Current state** (`packages/entity/src/Attribute/FieldTypeInferrer.php:144-148`):
```php
if (\class_exists($phpTypeName) && \is_subclass_of($phpTypeName, \BackedEnum::class)) {
    $settings['enum_class'] = $phpTypeName;
    return 'string';   // bridge — emits 'string' regardless of int-backing
}
```

**Target state**:
```php
if (\class_exists($phpTypeName) && \is_subclass_of($phpTypeName, \BackedEnum::class)) {
    $settings['enum_class'] = $phpTypeName;
    return 'enum';
}
```

Plus add `'enum'` to `VALID_TYPE_IDS` (`FieldTypeInferrer.php:27-44`).

The inferrer no longer cares about string-vs-int backing — that shifts to `EnumItem::schema()` (R6).

**Tests to update**: see R3 table.

---

## R5 — JSON Schema emission seam (FR-006)

**Decision**: Add a delegation seam on `FieldTypeManager` and call it from `FieldDefinition.toJsonSchema()`. This is a slightly bigger architectural change than the spec implied, but it is the only way for the plugin to own the contract.

**Current state**:
- `packages/field/src/FieldDefinition.php:88-120` — `toJsonSchema()` is a hardcoded `match` over the field-type id (`'string'`, `'integer'`, `'boolean'`, `'float'`, `'text'`, `'entity_reference'`) with `default => ['type' => 'string']`. It does NOT delegate to the field-type plugin's `jsonSchema()` method.
- `FieldTypeInterface::jsonSchema()` exists but is currently unused by the JSON Schema path. (`packages/field/src/FieldTypeInterface.php`)
- `packages/ai-schema/src/EntityJsonSchemaGenerator.php:26-92` — generates entity-level schema; for field-level schemas it relies on `FieldDefinition.toJsonSchema()`.

**Target state**:
- Add `FieldTypeManager::jsonSchemaFor(FieldDefinitionInterface $def): array` that resolves the plugin definition for `$def->getType()` and asks the plugin for a schema fragment, given the definition (so it can read `settings.enum_class`).
- Extend `FieldTypeInterface` with `jsonSchemaFor(FieldDefinitionInterface $def): array` (default base implementation returns `static::jsonSchema()` for back-compat with type-level schemas).
- Refactor `FieldDefinition.toJsonSchema()` to delegate to `FieldTypeManager::jsonSchemaFor()` for any registered type, falling back to the existing hardcoded `match` only for types absent from the plugin registry. (After this mission, all of `string`/`integer`/`boolean`/`float`/`text`/`entity_reference` should also have `jsonSchemaFor()` overrides — but only `enum` is in scope here. The hardcoded `match` stays as a backstop for those that don't yet override; that's NOT a transitional bridge under C-004 because it's pre-existing scaffolding, not the bridge being removed.)
- `EnumItem::jsonSchemaFor()` returns `['type' => 'string'|'integer', 'enum' => [...]]` based on the enum's backing type and case backing values.

**Rationale**:
- Without this seam, the plugin can't satisfy FR-006 (`enum: [...]` in the emitted schema).
- The change is additive on `FieldTypeInterface` (default implementation preserves behavior of existing items).

**Alternatives considered**:
- Hardcoding an `'enum'` arm in `FieldDefinition.toJsonSchema()` that reads `settings.enum_class`: rejected — duplicates the concern that should live on the plugin and re-introduces a bridge-shaped pattern.
- Refactoring all six existing field types to use `jsonSchemaFor()` in this mission: rejected — out of scope; doing it later won't introduce a bridge.

---

## R6 — Storage column resolution

**Decision**: `EnumItem::schema()` must read the enum backing type. Since `schema()` is a static method on the plugin class, it cannot see per-field settings. Resolution: introduce an instance-aware schema seam, or derive the column shape at the storage-schema-resolution layer rather than statically.

**Open question for implementation**: confirm whether the storage layer calls `schema()` statically or has a per-definition path. If static-only, the plan needs to extend the schema interface to accept the field definition (mirroring R5's `jsonSchemaFor`). The `FieldItemBase::schema(): array` precedent in `StringItem.php:31-36` and `IntegerItem.php:31-36` suggests a static call pattern, which would force the seam extension.

**Investigation note for the implementer**: trace `FieldTypeInterface::schema()` callers in `packages/field/` and `packages/entity-storage/` (if present). If storage already has a per-definition resolution path, use it; otherwise add `schemaFor(FieldDefinitionInterface $def): array` parallel to `jsonSchemaFor`.

**Rationale**:
- The spec mandates string column for string-backed and integer column for int-backed (FR-003); a static `schema()` cannot satisfy this.
- The seam shape (`schemaFor`) parallels `jsonSchemaFor`, keeping the plugin contract consistent.

**Alternatives considered**:
- Always store as `string` and cast at hydration: rejected — violates FR-003.
- Use a single TEXT/JSON column: rejected — defeats the point of using the backing scalar.

---

## R7 — Charter / governance check

`spec-kitty charter context --action plan --json` was not run as a separate step; planning has proceeded with the technical context from research above. If a charter exists for this repo, the plan-phase governance pass should run before tasks are finalized. (Action item: planner to run `spec-kitty charter context --action plan --json` before `/spec-kitty.tasks` and reconcile any findings.)

---

## Summary of resolved unknowns

| Spec Assumption / FR | Resolution |
|----------------------|-----------|
| Assumption 1 (plugin registration) | Auto-discovered via `#[FieldType]`; zero wiring needed beyond file placement. |
| Assumption 2 (widget label seam) | No existing seam; add static helper on `EnumItem` plus a one-method `LabeledCase` interface for enums that opt in. |
| Assumption 3 (no int-backed precedent) | Confirmed: no production entity classes use either shape today. |
| FR-006 (JSON Schema seam) | Add `jsonSchemaFor(FieldDefinitionInterface)` to `FieldTypeInterface`; delegate from `FieldDefinition.toJsonSchema()` via `FieldTypeManager`. |
| FR-009 (inferrer refactor) | Single emission site at `FieldTypeInferrer.php:144-148`; add `'enum'` to `VALID_TYPE_IDS` whitelist. |
| FR-010/FR-012 (consumer migration) | Limited to inferrer + constraint builder + tests + Field attribute docstring. No production entity classes affected. |
| FR-003 (storage column type) | Requires `schemaFor(FieldDefinitionInterface)` seam parallel to `jsonSchemaFor`; verify storage caller path during implementation. |

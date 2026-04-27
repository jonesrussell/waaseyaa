# Data Model: Enum Field-Type Plugin

**Mission**: `field-type-enum-plugin-01KQ6SJG`

This is a framework-internal mission; the "data model" is the plugin's settings shape, the storage column shape, the per-field schema fragment, and the case-label resolution model. There are no new entity tables.

---

## 1. Field settings (per-field, persisted)

Stored in the field definition's `settings` map.

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `enum_class` | string (FQCN of a `BackedEnum` subtype) | yes | The PHP enum class whose cases the field is constrained to. MUST be a class that exists, is an `enum`, and implements `\BackedEnum` with backing `string` or `int`. |

No other settings keys are read or written by `EnumItem`. Aliases (`enumClass`, `class`, `cases`) are explicitly **not supported** (C-003).

### Validation rules for the settings (FR-008)

| Rule | Failure mode |
|------|--------------|
| `enum_class` is set and non-null | Configuration error: "Field {field_name} (type=enum) requires settings.enum_class". |
| `class_exists(enum_class)` is true | Configuration error: "enum_class {fqcn} not found for field {field_name}". |
| `enum_class` is an enum (`(new ReflectionEnum($enum_class))->isBacked()`) | Configuration error: "enum_class {fqcn} is not a backed enum". |
| Backing type is `string` or `int` | Configuration error: "enum_class {fqcn} backing type must be string or int (got {backing_type})". |

These are checked at plugin first-use against the field definition, not in `defaultSettings()` (which is static and parameterless).

---

## 2. Storage column shape (per-field, derived)

Resolved by `EnumItem::schemaFor(FieldDefinitionInterface $def): array`.

| Backing type of `enum_class` | Returned schema |
|------------------------------|-----------------|
| `string` | `['value' => ['type' => 'varchar', 'length' => 255]]` |
| `int` | `['value' => ['type' => 'int']]` |

The string-backed length default (255) matches `StringItem`'s precedent (`packages/field/src/Item/StringItem.php:31-36`). A future iteration could read a `length` setting if larger backing values appear; out of scope for this mission.

---

## 3. JSON Schema fragment (per-field, derived)

Resolved by `EnumItem::jsonSchemaFor(FieldDefinitionInterface $def): array`.

For an enum class with cases `[A => 'a', B => 'b', C => 'c']` (string-backed):

```json
{
  "type": "string",
  "enum": ["a", "b", "c"]
}
```

For int-backed `[Low => 1, High => 9]`:

```json
{
  "type": "integer",
  "enum": [1, 9]
}
```

Cases appear in declaration order (the order returned by `EnumClass::cases()`). Empty enum (no cases): `{"type": "string"|"integer", "enum": []}` — any value will fail validation, per spec EC-5.

---

## 4. Coercion / hydration model

| Direction | Input | Output | Behavior |
|-----------|-------|--------|----------|
| Hydration (DB → entity) | scalar from storage column (string or int) | `\BackedEnum` instance | `enum_class::from($scalar)`. If the scalar isn't a valid case, raise a deterministic error attributable to the plugin, including field name and enum FQCN (NFR-002). |
| Mutation (entity → storage) | a `\BackedEnum` instance whose class is `$enum_class` | the case's backing scalar | `$value->value` |
| Mutation (entity → storage) | a string or int matching a case backing value | the same scalar (round-trip-validated) | `enum_class::from($scalar)` then `$case->value`; reject if not a case. |
| Mutation (entity → storage) | anything else (other enum class, array, object, null when required, etc.) | error | Validation error before storage (FR-005). |

The plugin never silently converts between backing types (e.g. numeric string `"1"` to int `1`); the input scalar must match the declared backing type.

---

## 5. Case label model (admin widgets, FR-007)

`EnumItem::casesForEnumClass(string $enumClass): array<string|int, string>`

Returns an ordered map of `case_backing_value => label`. Label resolution:

1. If `$enumClass` implements `Waaseyaa\Field\Item\LabeledCase` (defined in this mission), call `$case->getLabel()` per case.
2. Otherwise, fall back to the case's `name` property (PHP backed-enum's compile-time name, e.g. `Status::ACTIVE` → `"ACTIVE"`).

The helper does NOT translate, title-case, or otherwise humanize labels. Those concerns belong downstream (translators, admin theme).

```php
// packages/field/src/Item/LabeledCase.php (NEW, optional opt-in)
interface LabeledCase
{
    public function getLabel(): string;
}
```

Enums opt in:

```php
enum Status: string implements LabeledCase
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';

    public function getLabel(): string {
        return match ($this) {
            self::ACTIVE   => 'Currently active',
            self::ARCHIVED => 'Archived (read-only)',
        };
    }
}
```

---

## 6. New / extended type definitions in code

### Extended: `Waaseyaa\Field\FieldTypeInterface`

```php
public static function jsonSchemaFor(FieldDefinitionInterface $def): array;   // NEW
public static function schemaFor(FieldDefinitionInterface $def): array;       // NEW
```

Both have default implementations on `FieldItemBase` that delegate to the existing static `jsonSchema()` and `schema()`, preserving behavior for existing field types. `EnumItem` overrides both.

### Extended: `Waaseyaa\Field\FieldTypeManager`

```php
public function jsonSchemaFor(FieldDefinitionInterface $def): array;   // NEW — resolves plugin and forwards
public function schemaFor(FieldDefinitionInterface $def): array;       // NEW — resolves plugin and forwards
```

### Refactored: `Waaseyaa\Field\FieldDefinition::toJsonSchema()`

Replaces the hardcoded `match` (`packages/field/src/FieldDefinition.php:88-120`) with a delegation through `FieldTypeManager::jsonSchemaFor($this)`. The existing hardcoded shapes for `string`/`integer`/`boolean`/`float`/`text`/`entity_reference` move into the default `jsonSchemaFor()` override on each plugin (this part is OUT OF SCOPE for the current mission; the existing hardcoded `match` becomes a fallback when the resolved plugin returns the base default — kept as scaffolding, not a transitional bridge).

### Refactored: `Waaseyaa\Entity\Attribute\FieldTypeInferrer`

| Site | Change |
|------|--------|
| `FieldTypeInferrer.php:27-44` (`VALID_TYPE_IDS`) | Add `'enum'`. |
| `FieldTypeInferrer.php:144-148` | Replace `return 'string'` with `return 'enum'`. |

### Refactored: `Waaseyaa\Entity\Validation\FieldDefinitionConstraintBuilder`

| Site | Change |
|------|--------|
| `FieldDefinitionConstraintBuilder.php:67-78` | Replace direct `enum_class` setting read with delegation to the field-type plugin's validation contract for `'enum'` typed fields. The legacy branch that read `enum_class` from `'string'` fields is removed (C-004). |

---

## 7. Error taxonomy (NFR-002 surface)

| Code (suggested) | When raised | Message shape |
|------------------|-------------|---------------|
| `EnumFieldType.MissingEnumClass` | At first use, settings has no `enum_class`. | `"Field {field}: type=enum requires settings.enum_class"` |
| `EnumFieldType.UnknownEnumClass` | `enum_class` does not name an existing class. | `"Field {field}: enum_class {fqcn} not found"` |
| `EnumFieldType.NotABackedEnum` | Class exists but isn't a `BackedEnum`. | `"Field {field}: enum_class {fqcn} is not a backed enum"` |
| `EnumFieldType.UnsupportedBackingType` | Backing type is neither `string` nor `int`. | `"Field {field}: enum_class {fqcn} has unsupported backing type {type}"` |
| `EnumFieldType.InvalidStoredValue` | Hydration: scalar not in case set (covers EC-2). | `"Field {field}: stored value {value} is not a valid case of {fqcn}"` |
| `EnumFieldType.InvalidInputValue` | Mutation: input not a case or matching scalar. | `"Field {field}: value {value} is not a valid case of {fqcn}"` |

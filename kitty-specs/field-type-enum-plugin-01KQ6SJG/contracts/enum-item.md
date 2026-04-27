# Contract: `EnumItem` plugin

**File**: `packages/field/src/Item/EnumItem.php` (NEW)

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
    /**
     * Storage column shape for a specific field. Reads enum_class from the
     * field definition and selects varchar (string-backed) or int (int-backed).
     */
    public static function schemaFor(FieldDefinitionInterface $def): array;

    /**
     * JSON Schema fragment for waaseyaa/ai-schema. Returns
     *   ['type' => 'string'|'integer', 'enum' => [<case_value>, ...]]
     * where the case values are the backing scalars in declaration order.
     */
    public static function jsonSchemaFor(FieldDefinitionInterface $def): array;

    /**
     * Default settings: enum_class is required, default null (triggers config
     * error on first use if not overridden by the field definition).
     */
    public static function defaultSettings(): array;

    /**
     * Default value: null.
     */
    public static function defaultValue(): mixed;

    /**
     * Validates and coerces a scalar/case to a BackedEnum instance for storage.
     * Accepts:
     *   - a BackedEnum instance whose class === settings.enum_class
     *   - a scalar matching a declared case backing value
     * Rejects everything else with EnumFieldType.InvalidInputValue.
     *
     * Called during entity → storage mutation (FR-005).
     */
    public function castToCase(mixed $value, FieldDefinitionInterface $def): \BackedEnum;

    /**
     * Hydrates a stored scalar to a BackedEnum case. Raises
     * EnumFieldType.InvalidStoredValue if the scalar isn't a valid case
     * (covers EC-2: enum was edited after data was written).
     */
    public function hydrate(mixed $stored, FieldDefinitionInterface $def): \BackedEnum;

    /**
     * Returns case backing value → label map for admin widgets and the
     * constraint builder. Labels resolve via LabeledCase::getLabel() if the
     * enum implements that interface; otherwise fall back to the case name.
     *
     * Throws EnumFieldType.{UnknownEnumClass,NotABackedEnum,UnsupportedBackingType}
     * if $enumClass is invalid.
     */
    public static function casesForEnumClass(string $enumClass): array;
}
```

## Behavior assertions (testable, NFR-003)

1. **String-backed happy path** — A field declared with `enum_class = StringEnum::class` (cases `A='a'`, `B='b'`):
   - `schemaFor($def) === ['value' => ['type' => 'varchar', 'length' => 255]]`
   - `jsonSchemaFor($def) === ['type' => 'string', 'enum' => ['a', 'b']]`
   - `castToCase('a', $def) === StringEnum::A`
   - `castToCase(StringEnum::A, $def) === StringEnum::A`
   - `hydrate('b', $def) === StringEnum::B`

2. **Int-backed happy path** — Field with `IntEnum::class` (cases `Low=1`, `High=9`):
   - `schemaFor($def) === ['value' => ['type' => 'int']]`
   - `jsonSchemaFor($def) === ['type' => 'integer', 'enum' => [1, 9]]`
   - `castToCase(1, $def) === IntEnum::Low`

3. **Invalid stored scalar** — `hydrate('zzz', $def_for_StringEnum)` raises `EnumFieldType.InvalidStoredValue` mentioning `'zzz'`, `StringEnum::class`, and the field name.

4. **Invalid input value** — `castToCase('zzz', $def_for_StringEnum)` raises `EnumFieldType.InvalidInputValue`. `castToCase(IntEnum::Low, $def_for_StringEnum)` (wrong enum class) also raises that error.

5. **Invalid `enum_class` configuration**:
   - Class doesn't exist → `EnumFieldType.UnknownEnumClass`
   - Class is not an enum → `EnumFieldType.NotABackedEnum`
   - Class is a unit (non-backed) enum → `EnumFieldType.NotABackedEnum`
   - Class is a backed enum with non-string/int backing — not possible in PHP today, but the check is explicit (`EnumFieldType.UnsupportedBackingType`).

6. **Widget labels**:
   - `casesForEnumClass(LabeledStringEnum::class)` returns map with custom labels.
   - `casesForEnumClass(StringEnum::class)` (no `LabeledCase` impl) returns map with case names as labels.

7. **JSON Schema empty enum** — `jsonSchemaFor($def_for_EmptyEnum)` returns `['type' => 'string', 'enum' => []]` (EC-5).

# Contract: `FieldTypeInferrer` & `FieldDefinitionConstraintBuilder` migrations

## `FieldTypeInferrer`

**File**: `packages/entity/src/Attribute/FieldTypeInferrer.php`

### Change 1 — `VALID_TYPE_IDS` (lines 27–44)

Add `'enum'` to the constant array.

### Change 2 — Backed-enum emission (lines 144–148)

**Before**:

```php
if (\class_exists($phpTypeName) && \is_subclass_of($phpTypeName, \BackedEnum::class)) {
    $settings['enum_class'] = $phpTypeName;
    return 'string';
}
```

**After**:

```php
if (\class_exists($phpTypeName) && \is_subclass_of($phpTypeName, \BackedEnum::class)) {
    $settings['enum_class'] = $phpTypeName;
    return 'enum';
}
```

The inferrer is no longer responsible for choosing `string` vs `int` — that responsibility moves to `EnumItem::schemaFor()` based on the declared backing type.

### Behavior assertions

- For an attribute-first entity property typed as a string-backed enum, `FieldTypeInferrer::infer($property)` returns `['type' => 'enum', 'settings' => ['enum_class' => StringEnum::class]]`.
- For an int-backed enum property, the same shape with `IntEnum::class`.
- The inferrer never returns `'string'` paired with an `enum_class` setting.

### Tests to update

- `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php:78-85` — data-provider rows for `'BackedEnum → string + enum_class'` rename to `'BackedEnum → enum + enum_class'` and assert the new shape.
- `packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php:139-148` (`explicit_string_type_on_backed_enum_keeps_inferred_enum_class`): reconsider semantics — does an explicit `type: 'string'` annotation on a backed-enum property still produce `'string' + enum_class`, or is it now an error? Per C-004 (hard cutover) and the spec's single-canonical-shape stance, **this combination should be rejected** at inference time. Update or replace this test accordingly.

---

## `FieldDefinitionConstraintBuilder`

**File**: `packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php` (lines 67–78)

**Before** (paraphrased from research R3):

```php
$enumClass = $def->getSetting('enum_class') ?? $def->getSetting('enumClass');
if ($enumClass !== null) {
    $values = array_map(fn($case) => $case->value, $enumClass::cases());
    $constraints[] = new Choice(['choices' => $values]);
}
```

This branch reads `enum_class` from settings regardless of whether `$def->getType()` is `'string'` or anything else — the bridge.

**After**: scope the enum-validation logic to `$def->getType() === 'enum'`. Remove the `enumClass` alias read (settings shape is fixed per C-003). Delegate the case-value lookup to `EnumItem::casesForEnumClass()`:

```php
if ($def->getType() === 'enum') {
    $enumClass = $def->getSetting('enum_class');   // canonical key, no alias
    $values = array_keys(EnumItem::casesForEnumClass($enumClass));
    $constraints[] = new Choice(['choices' => $values]);
}
```

### Behavior assertions

- For a field declared with `type='enum', settings.enum_class=StringEnum::class`, the constraint builder produces a `Choice` constraint with `choices` equal to the case backing values.
- For a field declared with `type='string', settings.enum_class=...` (impossible after this mission, but defensive): the legacy branch is gone, so no `Choice` constraint is added on the `enum_class` basis. (Spec AS-8: such configurations should not be silently re-interpreted as enum.)
- For a field declared with `type='string'` and no `enum_class`, behavior is unchanged.

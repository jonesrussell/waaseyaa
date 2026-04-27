# Contract: `FieldTypeInterface` extensions

**Files modified**:
- `packages/field/src/FieldTypeInterface.php`
- `packages/field/src/FieldItemBase.php`
- `packages/field/src/FieldDefinition.php`
- `packages/field/src/FieldTypeManager.php`

## New interface methods

```php
// In FieldTypeInterface
public static function jsonSchemaFor(FieldDefinitionInterface $def): array;
public static function schemaFor(FieldDefinitionInterface $def): array;
```

## Default implementations on `FieldItemBase`

```php
public static function jsonSchemaFor(FieldDefinitionInterface $def): array
{
    return static::jsonSchema();   // back-compat: type-level schema
}

public static function schemaFor(FieldDefinitionInterface $def): array
{
    return static::schema();       // back-compat: type-level schema
}
```

These defaults preserve the behavior of every existing field type (`StringItem`, `IntegerItem`, `BooleanItem`, etc.) without requiring them to override.

## `FieldTypeManager` helpers

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

(`resolveItemClass` is the existing plugin lookup against the cached registry.)

## `FieldDefinition::toJsonSchema()` refactor

**Before** (`packages/field/src/FieldDefinition.php:88-120`): hardcoded `match` over `$this->type` returning fixed schemas.

**After**: delegate to the `FieldTypeManager`:

```php
public function toJsonSchema(): array
{
    return $this->fieldTypeManager->jsonSchemaFor($this);
}
```

`FieldDefinition` gains a `FieldTypeManager` reference (constructor injected). For existing types whose default `jsonSchemaFor` returns the type-level `jsonSchema()`, the output is bit-identical to today's hardcoded `match` — verified by the regression test added in WP1.

## Behavior assertions

1. For each existing field type id (`string`, `integer`, `boolean`, `float`, `text`, `entity_reference`), `FieldDefinition::toJsonSchema()` returns the same array it returned before this mission.
2. For an `enum`-typed field, `FieldDefinition::toJsonSchema()` returns `['type' => ..., 'enum' => [...]]`.
3. `FieldTypeManager::schemaFor($def_for_string_field)` returns the same shape as `StringItem::schema()` (proves the default delegation works).

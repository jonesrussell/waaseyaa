# waaseyaa/typed-data

**Layer 0 — Foundation**

Typed data validation and coercion for Waaseyaa applications.

Provides a type system for field and entity-adjacent values. **`EntityCastCoercion`** (`Waaseyaa\TypedData\Coercion\EntityCastCoercion`) is the canonical implementation for entity **`$casts`** builtins `int`, `float`, `bool`, `string`, and `array` (JSON string in storage): strict storage→domain and domain→storage rules shared with `waaseyaa/entity` **`ValueCaster`** (#1185). Failures throw **`CoercionException`**, which `ValueCaster` wraps as **`CastException`** for a single error model at the entity boundary.

**`CastTokenMapper`** maps entity cast tokens to `TypedDataManager` **`dataType`** strings (`int`→`integer`, etc.). Tokens `array` / `json` return `null` because JSON bags are not `map`/`list` without a schema.

Primitive types (`IntegerData`, `FloatData`, `BooleanData`, `StringData`) use **`getCastedValue()`** via the same coercion rules as entities (aligned with `ValueCaster`).

**Symfony Validator** applies **`DataDefinition`** constraints on the raw **`getValue()`** bag; coercion (`EntityCastCoercion` / `getCastedValue`) is separate from constraint validation—run both when you need normalized types *and* business rules.

Used by the field system (`FieldItemBase`, `PropertyValue`) and by entity casting (#1185).

Key classes: `TypedDataInterface`, `DataDefinition`, `TypedDataManager`, `EntityCastCoercion`, `CastTokenMapper`.

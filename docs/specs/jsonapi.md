# JSON:API — cast-aware attributes

<!-- Spec reviewed 2026-04-09 ST-9 - cast-aware ResourceSerializer pipeline, alignment with entity-system (#1181) -->

This document covers **how entity field values become JSON:API `attributes`** under the casting + hydration architecture (#1181). Full CRUD routing, documents, errors, query parsing, and schema endpoints remain specified in **`docs/specs/api-layer.md`**.

## Scope split

| Topic | Spec |
|-------|------|
| Routes, `JsonApiController`, sparse fieldsets, pagination, OpenAPI | `api-layer.md` |
| Attribute sourcing, `$casts`, JSON normalization for responses | This file + `entity-system.md` (Casting & hydration) |

## Attribute pipeline

`Waaseyaa\Api\ResourceSerializer` is the single authority for entity → `JsonApiResource` attributes.

```mermaid
flowchart TD
  E[EntityInterface] --> M[EntityValues::toCastAwareMap]
  M --> F[Filter entity keys id/uuid from attributes]
  F --> A[Field-definition coercion boolean/timestamp]
  A --> N[normalizeValueForJson enums DateTimeInterface arrays]
  N --> J[JsonApiResource.attributes]
```

### Invariants

1. **Cast-aware source:** Attributes are built from **`EntityValues::toCastAwareMap($entity)`** (internally: every key from `toArray()`, value from `get($key)`). This guarantees `EntityBase::$casts` apply before JSON shaping.
2. **Excluded keys:** Logical `id` and `uuid` column names from `EntityType::getKeys()` are omitted from `attributes`; they are represented as JSON:API `id` (UUID preferred, else numeric id as string; config entities use string machine name when UUID empty).
3. **Second pass — field definitions:** `castAttributes()` applies `boolean` and `timestamp`/`datetime` formatting on top of cast-aware values (e.g. `datetime_immutable` from `get()` → ISO string in payload).
4. **Third pass — JSON safety:** `normalizeAttributesForJson()` reduces `BackedEnum` to backing value, `UnitEnum` to name, `DateTimeInterface` to `ATOM`, recurses arrays and `JsonSerializable`.
5. **Field access:** When `EntityAccessHandler` + `AccountInterface` are both non-null, `filterFields(..., 'view', ...)` runs **after** the cast-aware map is built, on attribute keys.

### Anti-patterns

- Serializing from `$entity->toArray()` for public attributes when the entity defines `$casts` — enums and dates stay as raw storage scalars.
- Duplicating cast logic in controllers or the admin SPA — use API responses or shared server-side helpers (`EntityValues`).

### Example (conceptual)

```php
// Entity has protected array $casts = ['state' => MyBackedEnum::class];
// Storage / toArray(): state => 'active'
// JSON attribute after pipeline: "state" => "active" (backing value), not a PHP enum object
```

## Writes (`store` / `update`)

Incoming JSON:API attributes are applied with `$entity->set($field, $value)` (`JsonApiController::update`). **`set()` runs `castOut`**, so clients may send JSON-native scalars (strings, numbers, booleans) that match storage expectations; the entity persists storage-canonical values into `$values` before `toArray()` is snapshotted on save.

## Related specs

- `docs/specs/entity-system.md` — `ValueCaster`, hydration, `EntityValues`, config entities
- `docs/specs/api-layer.md` — `ResourceSerializer` API surface, paired nullable access pattern, CRUD flow

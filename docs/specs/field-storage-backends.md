# Field Storage Backends

<!-- Spec reviewed 2026-05-12 - authored as part of WP12 (entity-storage-v2-01KRCDDC) -->

This spec documents the pluggable field storage backend contract introduced by
mission `entity-storage-v2-01KRCDDC`. It is the authoritative reference for:

- The `FieldStorageBackendInterface` contract and obligations.
- Built-in backend ids and their storage strategies.
- Backend registration semantics.
- The type-mapping table (§8.2 of the mission spec).
- Conformance testing requirements (FR-049, FR-050).

---

## 1. Interface contract

```php
namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api
 *
 * A field storage backend persists and retrieves field values for entity
 * instances. Backends are registered per entity type via BackendRegistrar.
 * The EntityStorageCoordinator fans out read/write/delete operations to all
 * registered backends.
 */
interface FieldStorageBackendInterface
{
    /**
     * Return the stable, unique backend identifier.
     *
     * Must be idempotent — two calls must return the same non-empty string.
     * Framework-reserved ids are listed in ReservedBackendIds.
     */
    public function id(): string;

    /**
     * Read a single field value for the entity.
     *
     * Returns null when the entity does not exist in this backend's storage
     * or the field has no stored value. Must not throw on a missing entity.
     */
    public function read(EntityInterface $entity, FieldDefinition $field): mixed;

    /**
     * Write a single field value for the entity.
     *
     * Idempotent: writing value A then value B must store B, not both.
     * Must operate on an existing entity row (the coordinator guarantees
     * the row exists before calling write()).
     */
    public function write(EntityInterface $entity, FieldDefinition $field, mixed $value): void;

    /**
     * Remove all data this backend holds for the entity.
     *
     * After delete(), read() must return null for any field on this entity.
     * Must be idempotent — a second call must not throw.
     */
    public function delete(EntityInterface $entity): void;

    /**
     * Report whether this backend can satisfy a field-level predicate query.
     *
     * Called at definition-validation time. Returning false causes
     * DefinitionValidator to throw UnsupportedQueryException, routing the
     * query to a different backend or raising an error to the caller.
     *
     * sql-blob always returns false.
     * sql-column returns true for all non-vector field types.
     */
    public function supportsQuery(FieldDefinition $field, EntityQuery $query): bool;
}
```

---

## 2. Reserved backend identifiers

```php
namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api
 */
final class ReservedBackendIds
{
    public const SQL_BLOB   = 'sql-blob';
    public const SQL_COLUMN = 'sql-column';
    public const VECTOR     = 'vector';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SQL_BLOB, self::SQL_COLUMN, self::VECTOR];
    }
}
```

Third-party backends must choose an id not in `ReservedBackendIds::all()`. The
framework throws `BackendIdCollisionException` on registration collision.

---

## 3. Built-in backends

### 3.1 sql-blob (`SqlBlobBackend`)

- **Storage**: all field values serialized as JSON into a `_data` TEXT column on
  the entity table.
- **Schema**: no additional columns required; `SqlSchemaHandler` adds `_data`
  automatically.
- **Query support**: `supportsQuery()` always returns `false`. Queries against
  blob-stored fields must use the column-equality path in `SqlEntityStorage`.
- **delete()**: resets the `_data` blob to `{}`, leaving the entity row intact
  (the coordinator or storage layer issues the final `DELETE`).
- **Use when**: rapid prototyping, entities with few fields, or when column schema
  migrations are not yet ready.

### 3.2 sql-column (`SqlColumnBackend`)

- **Storage**: each `FieldDefinition` in its own SQL column on the entity table.
- **Schema**: managed by `SqlColumnSchemaBuilder`; `TypeMapping` maps field type
  strings to DBAL column types (see §5 below).
- **Query support**: `supportsQuery()` returns `true` for all non-vector field
  types. `float_vector_<n>` is rejected (must route to the vector backend).
- **delete()**: issues a `DELETE` statement on the entity row. Idempotent — if
  the row is already gone, the delete is a no-op.
- **Use when**: fields need indexed queries, type-safe column storage, or the
  sql-blob `_data` blob has become a query bottleneck.

---

## 4. Provider capability interfaces

```php
namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api
 *
 * Packages that ship custom backends implement this interface and return their
 * backend instances from fieldStorageBackends(). Discovery is via Composer
 * extra.waaseyaa.providers — no service locator.
 */
interface HasFieldStorageBackendsInterface
{
    /** @return list<FieldStorageBackendInterface> */
    public function fieldStorageBackends(): array;
}

/**
 * @api
 *
 * Marker for built-in framework backend providers.
 * Application code must NOT implement this interface.
 */
interface IsFrameworkBackendProviderInterface
{
}
```

---

## 5. Type-mapping table (§8.2)

The following table maps `FieldDefinition` type strings to DBAL column types
used by `SqlColumnBackend` via `TypeMapping`. Types not in this table cause
`UnmappedFieldTypeException` when a migration is generated.

| FieldDefinition type   | DBAL column type  | Notes                                          |
|----------------------- |------------------ |----------------------------------------------- |
| `string`               | `string`          | VARCHAR(255) default                           |
| `text`                 | `text`            | TEXT                                           |
| `integer`              | `integer`         | INT                                            |
| `bigint`               | `bigint`          | BIGINT                                         |
| `float`                | `float`           | FLOAT                                          |
| `decimal`              | `decimal`         | TEXT in SQLite (lossless); DECIMAL elsewhere   |
| `bool` / `boolean`     | `boolean`         | INTEGER 0/1 in SQLite; coerced to bool on read |
| `datetime`             | `datetime`        | TEXT (ISO 8601) in SQLite                      |
| `json`                 | `text`            | JSON-encoded string; decoded on read           |
| `entity_reference`     | `integer`         | Foreign key to target entity id                |
| `uuid`                 | `string`          | VARCHAR(128)                                   |

`float_vector_<n>` (e.g. `float_vector_1536`) is rejected by `SqlColumnBackend`
and must be routed to the vector backend.

---

## 6. Registration semantics

1. The framework pre-registers `sql-blob` and `sql-column` at boot.
2. Third-party packages implement `HasFieldStorageBackendsInterface` and declare
   their service provider in `composer.json extra.waaseyaa.providers`.
3. `BackendRegistrarFactory` creates a `BackendRegistrar` bound to a specific
   entity type. Entity-type-specific registration overrides global registration.
4. `FieldDefinition::getBackendId()` returns the preferred backend id for a
   field. When `null`, `BackendResolver` falls back to the entity type's
   `getPrimaryStorageBackend()`, then to `sql-blob`.

---

## 7. Failure modes

| Exception                        | When thrown                                                  |
|--------------------------------- |------------------------------------------------------------- |
| `UnknownBackendException`        | A field references a backend id that is not registered       |
| `UnsupportedQueryException`      | `supportsQuery()` returns `false` at definition-validation time |
| `UnsupportedListingException`    | Backend does not support listing operations                  |
| `PartialSaveException`           | At least one backend succeeds and at least one fails during save |
| `AbortOperationException`        | Thrown by a `BeforeSave`/`BeforeDelete` event listener to abort |
| `UnmappedFieldTypeException`     | Field type has no column mapping (migration generation only) |
| `BackfillRowCountMismatchException` | Backfill row count differs from expected (migration apply)  |

---

## 8. Coordinator and lifecycle events

`EntityStorageCoordinator` orchestrates fan-out:

1. Dispatch `BeforeSaveEvent` → any listener may throw `AbortOperationException`.
2. For each registered backend: call `write()` in registration order.
   - On partial failure: wrap in `PartialSaveException` and stop.
3. Dispatch `AfterSaveEvent` (only on full success).

Delete path mirrors save:

1. Dispatch `BeforeDeleteEvent`.
2. For each backend: call `delete()`.
3. Dispatch `AfterDeleteEvent`.

`CoordinatorLifecycleDispatcher` handles event dispatch. `SaveContext` carries
immutable revision flags through the save pipeline.

All lifecycle events implement `EntityLifecycleEventInterface`.

---

## 9. Conformance testing (FR-049, FR-050)

Any class implementing `FieldStorageBackendInterface` should extend
`FieldStorageBackendContractTestCase` (under
`Waaseyaa\EntityStorage\Testing\Contract`, autoload-dev from `testing/`).

Template methods the concrete test must implement:

| Method                  | Purpose                                                         |
|------------------------ |---------------------------------------------------------------- |
| `createBackend()`       | Return a fully wired backend against in-memory SQLite           |
| `prepareFixtureEntity()`| Return an entity whose row already exists in the database       |
| `fixtureField()`        | Return a `FieldDefinition` the backend can store                |
| `fixtureValue()`        | Return a non-null test value compatible with the field type     |
| `alternateValue()`      | Return a distinct second value for idempotent-rewrite test      |
| `supportsQueryField()`  | Return the field for the `supportsQuery()` contract test        |
| `expectSupportsQuery()` | Return the expected bool from `supportsQuery()`                 |

Inherited tests verified:

1. `idIsStableString` — `id()` returns a stable non-empty string (idempotent).
2. `readWriteDeleteRoundTrip` — write → read → delete → read returns null.
3. `idempotentRewrite` — write A then B; stored value is B.
4. `supportsQueryContract` — `supportsQuery()` returns declared bool.
5. `deleteCascade` — `delete()` idempotent; second call must not throw.

**Placement rule:** `FieldStorageBackendContractTestCase` lives under `testing/`
(NOT `src/`) and is registered under `autoload-dev` only. See CLAUDE.md:
"Never put classes that extend dev-only deps under autoload".

---

## 10. References

- `kitty-specs/entity-storage-v2-01KRCDDC/contracts/field-storage-backend.md` — original contract artifact
- `kitty-specs/entity-storage-v2-01KRCDDC/contracts/partial-save-error.md` — PartialSaveException contract
- `kitty-specs/entity-storage-v2-01KRCDDC/contracts/lifecycle-events.md` — lifecycle event contract
- `docs/specs/entity-system.md` §"Field storage backends" — overview in entity system spec
- `docs/upgrades/waaseyaa-alpha-X-to-Y.md` §3 — sql-blob → sql-column migration recipe
- Mission spec `kitty-specs/entity-storage-v2-01KRCDDC/spec.md` §8.2 (type mappings), §6.5 (partial save)

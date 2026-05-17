# Contract — Field Storage Backend

**Owning WPs**: WP01 (interface + registration), WP03 (`sql-blob`), WP05 (`sql-column`).
**Source**: spec §3.1, §5; ADR 010.
**Stable surface**: yes (charter §5.3).

---

## Interface

```php
namespace Waaseyaa\EntityStorage\Backend;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\EntityStorage\Query\EntityQuery;
use Waaseyaa\EntityStorage\Exception\UnsupportedQueryException;

/**
 * @api
 *
 * The single per-field storage strategy contract.
 *
 * Implementations are registered via {@see HasFieldStorageBackendsInterface}.
 * The id namespace is partitioned: `sql-blob`, `sql-column`, `vector` are
 * reserved by the framework. Apps and packages may register under any
 * non-reserved id.
 */
interface FieldStorageBackendInterface
{
    /** Stable backend id. Reserved: sql-blob, sql-column, vector. */
    public function id(): string;

    /** Read a single field value for an entity. Returns null when not stored. */
    public function read(EntityInterface $entity, FieldDefinition $field): mixed;

    /** Write a single field value for an entity. Idempotent on the backend's view. */
    public function write(EntityInterface $entity, FieldDefinition $field, mixed $value): void;

    /** Delete all values this backend holds for an entity (cascade across fields). */
    public function delete(EntityInterface $entity): void;

    /**
     * Declare whether this backend can satisfy a given query against a given field.
     *
     * MUST be called at definition-validation time, not query time. Backends
     * that cannot satisfy a query MUST throw {@see UnsupportedQueryException}
     * with a precise reason (e.g. "field not indexed", "operator not supported").
     */
    public function supportsQuery(FieldDefinition $field, EntityQuery $query): bool;
}
```

## Provider capability

```php
namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api
 *
 * Provider capability — packages that ship backends implement this and return
 * their backend instances. Discovery is via Composer; no service locator.
 */
interface HasFieldStorageBackendsInterface
{
    /**
     * @return list<FieldStorageBackendInterface>
     */
    public function fieldStorageBackends(): array;
}
```

## Reserved-id constant

```php
namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api — stable surface, charter §5.3.
 */
final class ReservedBackendIds
{
    public const SQL_BLOB = 'sql-blob';
    public const SQL_COLUMN = 'sql-column';
    public const VECTOR = 'vector';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SQL_BLOB, self::SQL_COLUMN, self::VECTOR];
    }
}
```

## Registration semantics

1. At boot, the framework collects all providers implementing `HasFieldStorageBackendsInterface` via Composer discovery (parallel to `HasNativeCommandsInterface`).
2. Each provider's `fieldStorageBackends()` is invoked; backends are indexed by `id()`.
3. Duplicate ids raise `BackendIdCollisionException` carrying BOTH registering FQCNs and the colliding id.
4. Reserved ids (`sql-blob`, `sql-column`, `vector`) MUST be registered only by the framework. Third-party packages registering reserved ids MUST fail boot.
5. Registration order across packages: Composer `installed.json` install order. Providers MAY declare an integer `priority` constant on the capability; higher priority wins ties. (Resolution to spec §16.1.)

## Failure modes

| Condition | Exception | Where raised |
|---|---|---|
| Two providers register the same id | `BackendIdCollisionException` | Boot, in registrar. |
| Backend declared `id()` is reserved but provider is not the framework | `BackendIdCollisionException` (subclass or distinct code) | Boot. |
| Backend's `supportsQuery()` returns false for a definition that uses the backend with non-trivial query | `UnsupportedQueryException` | Definition-validation phase (NOT query time). |

## Test surface

- Contract-test base class `FieldStorageBackendContractTestCase` (WP12) — every concrete backend MUST extend it. Verifies: id stability, read/write/delete round-trip, idempotent re-write, supportsQuery contract.
- See `contracts/lifecycle-events.md` for the events backends do NOT emit (coordinator does).

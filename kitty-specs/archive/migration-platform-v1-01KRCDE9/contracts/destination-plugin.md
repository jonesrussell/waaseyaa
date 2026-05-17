# Contract — Destination Plugin

**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Spec sections:** §3.3, §3.6 (FR-005, FR-006, FR-018, FR-019, FR-020, FR-021, FR-022, FR-023, FR-024, FR-029, FR-041, FR-042), §5.3, §7, §10.2.
**Owning WPs:** WP01 (interface + DTOs), WP05 (`EntityDestination` write path), WP08 (rollback path).
**Charter anchor:** §5.8 (new — Migration platform).

This document is the normative contract for `DestinationPluginInterface`, its DTOs (`DestinationRecord`, `WriteResult`), the conformance gate, and the `SaveContext::isImport()` wiring through the entity-storage coordinator.

---

## Interface

```php
namespace Waaseyaa\Migration\Plugin;

use Waaseyaa\Migration\DTO\DestinationRecord;
use Waaseyaa\Migration\DTO\WriteResult;
use Waaseyaa\Migration\IdMap\SourceId;

/**
 * @api
 *
 * A destination plugin writes a single processed record into a Waaseyaa-side
 * target (entity, config, external system). The framework ships exactly one
 * stable destination — EntityDestination. Third-party packages MAY ship more.
 */
interface DestinationPluginInterface
{
    public function id(): string;

    /** @return 'stable'|'experimental' */
    public function stability(): string;

    /**
     * Write a record. MUST be idempotent in conjunction with the id-map: calling
     * write() twice with the same effective DestinationRecord (i.e. same
     * source_record_hash) yields the same WriteResult and produces no
     * duplicate destination entity.
     */
    public function write(DestinationRecord $record): WriteResult;

    /**
     * Reverse a prior write. MUST be best-effort: on error, the destination
     * SHOULD log on entity.lifecycle and return without raising (the runner's
     * rollback walk continues per FR-044).
     */
    public function rollback(WriteResult $result): void;

    /**
     * Consult the id-map for a prior write of this SourceId. Returns null if
     * no prior write recorded.
     */
    public function lookup(SourceId $sourceId): ?WriteResult;
}
```

### `DestinationRecord` DTO

```php
namespace Waaseyaa\Migration\DTO;

use Waaseyaa\Migration\IdMap\SourceId;

/**
 * @api
 */
final readonly class DestinationRecord
{
    public function __construct(
        public string $migrationId,
        public SourceId $sourceId,
        /** @var array<string, mixed> field name → processed value */
        public array $fields,
        public string $sourceRecordHash,
    ) {}
}
```

### `WriteResult` DTO

```php
namespace Waaseyaa\Migration\DTO;

/**
 * @api
 *
 * Returned by DestinationPluginInterface::write(). Carries enough information
 * for rollback and for cross-migration LookupProcessor resolution (FR-028).
 */
final readonly class WriteResult
{
    public function __construct(
        public string $migrationId,
        public SourceId $sourceId,
        public string $destinationEntityType,
        public string $destinationUuid,
        public string $sourceRecordHash,
        public \DateTimeImmutable $writtenAt,
        public string $runId,                 // UUIDv7
    ) {}
}
```

---

## `EntityDestination` semantics (FR-018..FR-024, FR-042)

`EntityDestination` is the only stable-surface destination shipped in this mission.

### Construction

```php
new EntityDestination(
    entityType: 'teaching',
    bundle: 'wordpress_import',
    langcode: 'en',
)
```

Bundle resolves at write time, not registration (FR-024).

### Write path (spec §7.2)

1. Resolve `EntityTypeInterface` from `EntityTypeManager`.
2. Check `create` access via `Gate::denies()` (FR-020). Denial → `DestinationWriteException` (code `entity_create_denied`).
3. Look up `migration_id_map` row by `(migrationId, sourceId->hash())`.
   - Row exists, `source_record_hash` matches → return the existing `WriteResult` (skip write; FR-031).
   - Row exists, hash differs → load entity by uuid, update fields, save (creates a new revision for revisionable types — FR-023).
   - No row → create new entity.
4. Construct `SaveContext` with `isImport: true` (FR-022).
5. Call `EntityRepository::save($entity, $saveContext)`. The coordinator (M-001):
   - Fans out across backends per ADR 010 (FR-019).
   - Dispatches `BeforeSaveEvent` then `AfterSaveEvent` with the `SaveContext` carrying `isImport === true` (FR-021).
   - Creates an initial revision for revisionable entity types (FR-023).
6. UPSERT id-map row `(migration_id, source_id_hash, destination_entity_type, destination_uuid, last_imported_at, last_run_id, source_record_hash)` inside the same DBAL transaction (FR-029).
7. Return a `WriteResult` carrying the entity's uuid + the source-record hash + the run id.

### Rollback path (spec §7.4, FR-042)

1. Resolve the entity from `(destination_entity_type, destination_uuid)`.
2. Check `delete` access via `Gate::denies()`. Denial → log on `entity.lifecycle`, return (best-effort per FR-044).
3. Call `EntityRepository::delete($entity)`. Lifecycle events fire normally (`BeforeDeleteEvent`, `AfterDeleteEvent`).
4. DELETE the id-map row inside the same transaction.

### Lookup path (FR-028)

```php
public function lookup(SourceId $sourceId): ?WriteResult
{
    $row = $this->idMap->lookupDestination($this->migrationId, $sourceId);
    return $row;  // already shaped as WriteResult or null
}
```

`MigrationIdMap::lookupDestination()` is the underlying API (data-model §1.7); `EntityDestination::lookup()` delegates to it.

---

## `SaveContext::isImport()` wiring (FR-022)

`SaveContext` is the per-save context object shipped in M-001. The migration platform adds one additive method:

```php
// packages/entity-storage/src/SaveContext.php  (MODIFY)
final readonly class SaveContext
{
    public function __construct(
        // ... existing constructor args ...
        private bool $isImport = false,
    ) {}

    public function isImport(): bool
    {
        return $this->isImport;
    }
}
```

`EntityDestination::write()` constructs a `SaveContext` with `isImport: true`. The coordinator threads that context into `BeforeSaveEvent` and `AfterSaveEvent` constructors. Subscribers detect imports by calling `$event->context->isImport()`.

Subscribers that want to skip work during imports (e.g. expensive cache invalidation, analytics) check the flag and branch. Non-aware subscribers see the flag default to `false` and behave as before — additive, no break.

---

## Semantic invariants

1. **Idempotency on re-run** (FR-030, FR-031). `write()` must respect the id-map; same `SourceId` + same `source_record_hash` → no new entity, return the prior `WriteResult`.
2. **Transactional id-map** (FR-029). The id-map UPSERT and the entity save MUST share a transaction. On entity save failure, the id-map row MUST roll back too (R1 mitigation).
3. **Access policies are authoritative** (FR-020, FR-042). The destination MUST consult `Gate::denies()` for `create` (write) and `delete` (rollback). Denials raise typed exceptions on write, log-and-skip on rollback.
4. **Lifecycle events fire** (FR-021). `write()` dispatches `BeforeSaveEvent` + `AfterSaveEvent` via the coordinator; `rollback()` dispatches `BeforeDeleteEvent` + `AfterDeleteEvent`.
5. **Revisions on revisionable types** (FR-023). First import creates initial revision. Re-runs with changed `source_record_hash` create a new revision. Unchanged source records create no revision.
6. **Rollback is best-effort** (FR-044). Per-record rollback failure is logged but does NOT raise out of `rollback()`. The runner's walk continues.

---

## Error conditions

| Error | When | Type | Code |
|---|---|---|---|
| Access denied on create | `Gate::denies('create', ...)` returns true | `DestinationWriteException` | `entity_create_denied` |
| Coordinator raises `PartialSaveException` | mid-fan-out backend failure | `DestinationWriteException` (wraps prior) | `entity_partial_save` |
| Entity-type unknown | `entityType` not in `EntityTypeManager` | `DestinationWriteException` | `entity_type_unknown` |
| Bundle resolution fails | bundle declared but does not exist on the type | `DestinationWriteException` | `entity_bundle_unknown` |
| Validation fails | `EntityValidator` rejects | `DestinationWriteException` (wraps `EntityValidationException`) | `entity_validation_failed` |

`DestinationWriteException` extends a base `MigrationException` interface, carries `string $code`, exposes `getPrevious()` for the wrapped coordinator exception.

---

## Conformance requirements (WP10)

`DestinationConformanceTestCase` is an abstract `TestCase`. Subclasses provide a fixture destination plugin. The base class runs:

| # | Test | Spec |
|---|---|---|
| C1 | `write()` returns a `WriteResult` whose `$destinationUuid` is non-empty. | FR-006, §10.2 |
| C2 | Writing the same `DestinationRecord` twice creates exactly one destination entity (idempotency). | FR-030, §10.2 |
| C3 | After `write()`, `lookup($sourceId)` returns the same `WriteResult`. | FR-028 |
| C4 | After `rollback($result)`, `lookup($sourceId)` returns `null` **for destinations whose `rollback()` clears the id-map row** (framework default). Destinations that intentionally retain id-map rows for audit/replay MAY opt out by overriding `DestinationConformanceTestCase::rollbackClearsLookup()` to `false`; in that mode the harness asserts only that `rollback()` executes without raising. | FR-041, FR-042, §10.2 |

**Reconciliation with FR-042 (issue #1452).** FR-042 ("Re-running an unchanged record MUST NOT create a duplicate destination entity. The id-map row's `last_imported_at` SHOULD update.") governs the **idempotent re-run** code path — a separate scenario from `rollback()`. C4 governs the **rollback** code path. The two paths are operationally distinct: rollback is an entity delete; an unchanged re-run is a skip-or-touch on a still-extant entity. Whether `rollback()` deletes the id-map row alongside the entity is an implementation-defined retention policy:

- **Framework default** (`EntityDestination` and any plugin where `rollbackClearsLookup() === true`): `rollback()` removes the id-map row via `MigrationIdMap::deleteByDestination()`, so subsequent `lookup($sourceId)` returns `null`.
- **Retain-for-audit destinations** (`rollbackClearsLookup() === false`): `rollback()` deletes the destination entity but leaves the id-map row in place so the audit log can replay the prior import. `lookup($sourceId)` MAY return the prior `WriteResult` even after `rollback()`.

Both modes are conformant. Third-party authors choose by overriding `rollbackClearsLookup()`; the harness gates accordingly. FR-042's id-map retention requirement on unchanged re-run is independent of this choice — both modes still satisfy FR-042.
| C5 | A simulated access-denial raises `DestinationWriteException` with code `entity_create_denied`. | FR-020 |
| C6 | The id-map row written by `write()` survives `EntityRepository::save()` success; an injected save failure leaves zero id-map rows (R1). | FR-029 |
| C7 | `SaveContext::isImport()` returns `true` inside the `BeforeSaveEvent` and `AfterSaveEvent` dispatched during `write()`. | FR-022 |
| C8 | Writing a `DestinationRecord` with an unchanged `source_record_hash` returns the prior `WriteResult` without invoking `EntityRepository::save()` again. | FR-031 |

### Test surface

`DestinationConformanceTestCase` lives under `packages/migration/testing/` (autoload-dev only). Subclasses MUST implement:

```php
abstract protected function buildDestinationUnderTest(): DestinationPluginInterface;
abstract protected function buildSampleRecord(SourceId $id, string $hash): DestinationRecord;
abstract protected function injectSaveFailure(): void;
```

---

## Out of scope (FR boundaries)

- **Bulk-insert optimizations.** v0.x writes per-record. Batch inserts revisit if WP11 throughput target (≥1000 records/min) is missed.
- **Content-promotion semantics.** Migrations are inbound. Content promotion between Waaseyaa environments is a fixture/seed concern (spec §1.2).
- **Cross-destination atomicity.** `write()` is per-record. If a single source record needs to write to two destinations atomically, that's a multi-migration concern (out of scope; revisit if real workloads demand).

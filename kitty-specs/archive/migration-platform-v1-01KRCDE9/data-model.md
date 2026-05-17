# Data Model — Migration Platform v1

**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Phase:** 1 — Design & Contracts
**Status:** COMPLETE.

Every symbol below is governed by **charter §5.8** (proposed; landed in WP12 — additive amendment). The mission's spec §4 lists every entry; this document expands each with file path, FR anchor, and owning WP.

---

## 1. Stable-surface symbols (per spec §4)

### 1.1 Plugin interfaces

| Symbol | File | FR | WP |
|---|---|---|---|
| `Waaseyaa\Migration\Plugin\SourcePluginInterface` | `packages/migration/src/Plugin/SourcePluginInterface.php` | FR-001, FR-002 | WP01 |
| `Waaseyaa\Migration\Plugin\ProcessPluginInterface` | `packages/migration/src/Plugin/ProcessPluginInterface.php` | FR-003, FR-004 | WP01 |
| `Waaseyaa\Migration\Plugin\DestinationPluginInterface` | `packages/migration/src/Plugin/DestinationPluginInterface.php` | FR-005, FR-006 | WP01 |

### 1.2 Provider capabilities

| Symbol | File | FR | WP |
|---|---|---|---|
| `Waaseyaa\Migration\Capability\HasMigrationPluginsInterface` | `packages/migration/src/Capability/HasMigrationPluginsInterface.php` | FR-007 | WP01 |
| `Waaseyaa\Migration\Capability\HasMigrationsInterface` | `packages/migration/src/Capability/HasMigrationsInterface.php` | FR-013 | WP02 |

### 1.3 Manifest value object

| Symbol | File | FR | WP |
|---|---|---|---|
| `Waaseyaa\Migration\Definition\MigrationDefinition` | `packages/migration/src/Definition/MigrationDefinition.php` | FR-011, FR-012, FR-016 | WP02 |

### 1.4 Process plugin concretes (six reserved-id classes)

| Symbol | Reserved id | File | FR | WP |
|---|---|---|---|---|
| `PassThroughProcessor` | `pass_through` | `packages/migration/src/Process/PassThroughProcessor.php` | spec §5.4, FR-009 | WP03 |
| `HtmlSanitizeProcessor` | `html_sanitize` | `packages/migration/src/Process/HtmlSanitizeProcessor.php` | spec §5.4 | WP03 |
| `LookupProcessor` | `lookup` | `packages/migration/src/Process/LookupProcessor.php` | spec §5.4, FR-028 | WP03 |
| `ConcatProcessor` | `concat` | `packages/migration/src/Process/ConcatProcessor.php` | spec §5.4 | WP03 |
| `TypeCoerceProcessor` | `type_coerce` | `packages/migration/src/Process/TypeCoerceProcessor.php` | spec §5.4 | WP03 |
| `DefaultValueProcessor` | `default_value` | `packages/migration/src/Process/DefaultValueProcessor.php` | spec §5.4 | WP03 |

### 1.5 Destination concrete

| Symbol | File | FR | WP |
|---|---|---|---|
| `Waaseyaa\Migration\Destination\EntityDestination` | `packages/migration/src/Destination/EntityDestination.php` | FR-018, FR-019, FR-020, FR-021, FR-022, FR-023, FR-024, FR-042 | WP05 (write) + WP08 (rollback) |

### 1.6 DTO value objects

| Symbol | File | FR | WP |
|---|---|---|---|
| `Waaseyaa\Migration\IdMap\SourceId` | `packages/migration/src/IdMap/SourceId.php` | FR-026, FR-027 | WP04 |
| `Waaseyaa\Migration\DTO\SourceRecord` | `packages/migration/src/DTO/SourceRecord.php` | FR-002 | WP01 |
| `Waaseyaa\Migration\DTO\DestinationRecord` | `packages/migration/src/DTO/DestinationRecord.php` | FR-006 | WP01 |
| `Waaseyaa\Migration\DTO\WriteResult` | `packages/migration/src/DTO/WriteResult.php` | FR-006, FR-028 | WP01 |
| `Waaseyaa\Migration\DTO\ProcessContext` | `packages/migration/src/DTO/ProcessContext.php` | FR-004 | WP01 |

### 1.7 Storage schemas

| Schema | File (migration) | FR | WP |
|---|---|---|---|
| `migration_id_map` table | `packages/migration/src/IdMap/Schema/MigrationIdMapMigration.php` | FR-025, FR-028, FR-031 | WP04 |
| `migration_run_state` table | `packages/migration/src/Runner/Schema/MigrationRunStateMigration.php` | FR-038 | WP07 |

### 1.8 Exceptions

| Symbol | File | FR | WP |
|---|---|---|---|
| `MigrationCycleException` | `packages/migration/src/Exception/MigrationCycleException.php` | FR-015, FR-045 | WP02 |
| `MigrationPluginCollisionException` | `packages/migration/src/Exception/MigrationPluginCollisionException.php` | FR-008, FR-017, FR-045 | WP01 |
| `MigrationDependencyMissingException` | `packages/migration/src/Exception/MigrationDependencyMissingException.php` | FR-014, FR-045 | WP02 |
| `SourceReadException` | `packages/migration/src/Exception/SourceReadException.php` | FR-045 | WP01 |
| `ProcessException` | `packages/migration/src/Exception/ProcessException.php` | FR-045 | WP01 |
| `DestinationWriteException` | `packages/migration/src/Exception/DestinationWriteException.php` | FR-020, FR-045 | WP01 |
| `MigrationAbortedException` | `packages/migration/src/Exception/MigrationAbortedException.php` | FR-045, FR-048 | WP06 |
| `MigrationConcurrencyException` | `packages/migration/src/Concurrency/MigrationConcurrencyException.php` | FR-061, FR-062 | WP09 |

Every exception carries a stable `string $code` field (FR-045) — the code is what tools / log analyzers index on, not the FQCN.

### 1.9 Coordinator surface extension

| Symbol | File | FR | WP |
|---|---|---|---|
| `Waaseyaa\EntityStorage\SaveContext::isImport(): bool` | `packages/entity-storage/src/SaveContext.php` (MODIFY — additive method) | FR-022 | WP05 |

This is the single touch to a pre-existing class. The method is additive; existing call sites do not break.

### 1.10 CLI commands

| Command | File | FR | WP |
|---|---|---|---|
| `import:run` | `packages/cli/src/Command/ImportRunCommand.php` | FR-032, FR-039, FR-040, FR-047 | WP06 |
| `import:run-all` | `packages/cli/src/Command/ImportRunAllCommand.php` | FR-033, FR-039, FR-040 | WP06 |
| `import:status` | `packages/cli/src/Command/ImportStatusCommand.php` | FR-034 | WP06 |
| `import:rollback` | `packages/cli/src/Command/ImportRollbackCommand.php` | FR-035, FR-043, FR-044 | WP08 |
| `import:reset` | `packages/cli/src/Command/ImportResetCommand.php` | FR-036 | WP08 |
| `import:resume` | `packages/cli/src/Command/ImportResumeCommand.php` | FR-037, FR-038 | WP07 |

### 1.11 Conformance test bases

| Symbol | File | FR | WP |
|---|---|---|---|
| `SourceConformanceTestCase` | `packages/migration/testing/SourceConformanceTestCase.php` (autoload-dev only) | FR-049, FR-051 | WP10 |
| `DestinationConformanceTestCase` | `packages/migration/testing/DestinationConformanceTestCase.php` (autoload-dev only) | FR-050, FR-051 | WP10 |

These live under `testing/` (not `src/`) to keep them out of production autoload — see CLAUDE.md gotcha "Never put classes that extend dev-only deps under autoload."

### 1.12 Channel constants

| Symbol | File | FR | WP |
|---|---|---|---|
| `Waaseyaa\Migration\Log\Channels::DEPRECATION` (`'migration.deprecation'`) | `packages/migration/src/Log/Channels.php` | FR-009, spec §4 | WP01 |

The `entity.lifecycle` channel (owned by entity-storage) is reused unchanged for access-denial and rollback-best-effort log lines (FR-020, FR-044).

---

## 2. Plugin registration model

### 2.1 `HasMigrationPluginsInterface`

```php
namespace Waaseyaa\Migration\Capability;

use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;

/**
 * @api
 *
 * Provider capability that surfaces source/process/destination plugins.
 * Discovered via Composer-based provider scanning (same mechanism as HasNativeCommandsInterface).
 */
interface HasMigrationPluginsInterface
{
    /**
     * @return array<SourcePluginInterface|ProcessPluginInterface|DestinationPluginInterface>
     */
    public function migrationPlugins(): array;
}
```

One method returns all three categories disambiguated by `instanceof` at registration time.

### 2.2 `HasMigrationsInterface`

```php
namespace Waaseyaa\Migration\Capability;

use Waaseyaa\Migration\Definition\MigrationDefinition;

/**
 * @api
 *
 * Provider capability that surfaces concrete MigrationDefinition instances.
 */
interface HasMigrationsInterface
{
    /** @return array<MigrationDefinition> */
    public function migrations(): array;
}
```

### 2.3 Boot-time registration sequence (WP01 + WP02)

1. `MigrationRegistry::boot()` iterates providers (Composer-discovered).
2. For each provider implementing `HasMigrationPluginsInterface`, call `migrationPlugins()`; index each plugin by `id()`. Duplicate id → `MigrationPluginCollisionException` (FR-008) carrying both registering FQCNs.
3. Reserved plugin-ids (`pass_through`, `html_sanitize`, `lookup`, `concat`, `type_coerce`, `default_value`) may only be registered by `Waaseyaa\Migration\*` providers; third-party registration → `MigrationPluginCollisionException` with reserved-id flag.
4. For each provider implementing `HasMigrationsInterface`, call `migrations()`; index each definition by `MigrationDefinition::$id`. Duplicate id → `MigrationPluginCollisionException` (FR-017 — ids share a namespace with plugin-ids).
5. Filesystem manifests at `config/waaseyaa.php['migration']['manifest_paths']` are loaded same pass (FR-013).
6. Resolve each definition's `dependencies[]` against the registry. Missing → `MigrationDependencyMissingException` (FR-014).
7. Build dependency DAG via `DependencyGraph` + `CycleDetector`. Cycle → `MigrationCycleException` (FR-015) carrying the cycle path.

Registration is one-shot at boot; the registry is immutable after `boot()` returns.

---

## 3. `MigrationDefinition` shape

```php
namespace Waaseyaa\Migration\Definition;

use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;

/**
 * @api
 */
final readonly class MigrationDefinition
{
    public function __construct(
        public string $id,
        public SourcePluginInterface $source,
        /** @var array<string, ProcessPluginInterface|string|array<ProcessPluginInterface|string>> */
        public array $process,
        public DestinationPluginInterface $destination,
        /** @var string[] */
        public array $dependencies = [],
        public ?string $description = null,
        public int $memoryBudgetBytes = 268_435_456,    // 256 MiB (Q4 resolution)
        public float $errorRateWarn = 0.01,              // Q5 resolution
        public float $errorRateHalt = 0.10,              // Q5 resolution
    ) {}
}
```

### Process-map shape (FR-016)

The `$process` array's key is the **destination field name**. Each value is one of:

- A `ProcessPluginInterface` instance — runs that processor on the source value.
- A `string` — interpreted as a source field name; `PassThroughProcessor` runs on that source field.
- An array — chain: processors run in array order; output of N is input to N+1 (FR-010).

Example:

```php
'process' => [
    'title' => 'post_title',                                          // PassThrough on post_title
    'body'  => new HtmlSanitizeProcessor('post_content'),             // sanitize post_content
    'author_id' => new LookupProcessor(                                // resolve via id-map
        sourceField: 'post_author',
        migration: 'wp_users_to_accounts',
    ),
    'slug'  => [                                                       // chain
        new ConcatProcessor(['post_slug', '-archive']),
        new TypeCoerceProcessor('string'),
    ],
],
```

Chain ordering is array-order only in v0.x (Q3 resolution). No `Pipeline::after()/before()` mechanism.

---

## 4. Storage shape

### 4.1 `migration_id_map` (FR-025, spec §8.1)

```sql
CREATE TABLE migration_id_map (
    migration_id            TEXT NOT NULL,
    source_id_hash          TEXT NOT NULL,
    destination_entity_type TEXT NOT NULL,
    destination_uuid        TEXT NOT NULL,
    last_imported_at        TEXT NOT NULL,    -- ISO 8601 UTC
    last_run_id             TEXT NOT NULL,    -- UUIDv7 per record (R4 mitigation)
    source_record_hash      TEXT NOT NULL,    -- D6: change-detection hash
    PRIMARY KEY (migration_id, source_id_hash)
);
CREATE INDEX migration_id_map__entity
    ON migration_id_map (destination_entity_type, destination_uuid);
```

Both indexes are stable surface per spec §8.1. Future changes follow charter §5.4 (schema evolution).

### 4.2 `migration_run_state` (FR-038 — proposed, mission-internal)

```sql
CREATE TABLE migration_run_state (
    migration_id      TEXT NOT NULL,
    source_id_hash    TEXT NOT NULL,
    run_id            TEXT NOT NULL,                  -- UUIDv7 per CLI invocation
    item_status       TEXT NOT NULL,                  -- 'success' | 'error' | 'skipped'
    error_code        TEXT NULL,
    error_message     TEXT NULL,
    position          INTEGER NOT NULL,               -- monotonically increasing per run
    updated_at        TEXT NOT NULL,                  -- ISO 8601 UTC
    PRIMARY KEY (migration_id, source_id_hash)
);
CREATE INDEX migration_run_state__run
    ON migration_run_state (migration_id, run_id, position);
```

Notes:

- `item_status` is named (not `status`) per `.claude/rules/shell-compatibility.md`-equivalent caution about reserved identifiers; also avoids confusion with the per-migration aggregate "state" surfaced by `import:status`.
- `position` is the monotonically increasing per-run counter — `import:resume` reads `MAX(position)` for `(migration_id, run_id)` to compute the restart point.
- Schema is **mission-internal infrastructure**, not charter §5.8 stable surface. Future schema changes do not require charter amendment.

---

## 5. `EntityDestination` semantics

### 5.1 Construction (FR-024)

```php
new EntityDestination(
    entityType: 'teaching',
    bundle: 'wordpress_import',
    langcode: 'en',
)
```

Bundle resolves at write time, not registration (D8).

### 5.2 Write path (FR-018, FR-019, FR-020, FR-021, FR-022, FR-023, FR-029)

1. Resolve the entity type from `EntityTypeManager`.
2. Check access: `Gate::denies('create', $entityType, $account)`. Denial → `DestinationWriteException` with code `entity_create_denied` (FR-020).
3. Compute `source_record_hash` from the `DestinationRecord` canonical form.
4. Lookup `migration_id_map` row for `(migration_id, source_id_hash)`:
   - Row exists, `source_record_hash` matches → skip (idempotent re-run; FR-031).
   - Row exists, hash differs → load the entity by uuid, update fields, save (creates new revision for revisionable types per FR-023).
   - No row → standard create.
5. Construct a `SaveContext` with `isImport: true` (FR-022).
6. Call `EntityRepository::save($entity, $saveContext)`. The coordinator:
   - Fans out across backends (ADR 010).
   - Dispatches `BeforeSaveEvent` and `AfterSaveEvent` with the `SaveContext` carrying `isImport === true` (FR-021).
   - Creates an initial revision for revisionable entity types (FR-023, ADR 016).
7. UPSERT the id-map row with `(last_imported_at, last_run_id, source_record_hash)`.
8. Return `WriteResult(uuid: $entity->uuid, sourceRecordHash: ...)`.

Atomicity: steps 6 + 7 share a DBAL transaction (`Connection::transactional()` per FR-029). On exception, both roll back.

### 5.3 Rollback path (FR-042)

1. Lookup id-map row by `(migration_id, source_id_hash)` (from the `WriteResult`).
2. Resolve the entity by `(destination_entity_type, destination_uuid)`.
3. Check access: `Gate::denies('delete', $entity, $account)`. Denial → log on `entity.lifecycle`, skip (rollback is best-effort per FR-044).
4. Call `EntityRepository::delete($entity)`. The coordinator dispatches `BeforeDeleteEvent` and `AfterDeleteEvent` (M-001).
5. DELETE the id-map row inside the same transaction.

---

## 6. Streaming source semantics (FR-002, spec §5.1)

`SourcePluginInterface::records(): iterable` MUST be a generator or other lazy iterable. Implementations MUST NOT eager-load the full source dataset.

`SourceConformanceTestCase` (WP10) enforces this by importing a fixture larger than 50MB and asserting `memory_get_peak_usage(true)` stays under 50MB (FR-051). Failure of this gate is a hard block on shipping any new source-reader package.

`count(): ?int` returns `null` when the source cannot pre-compute a total (e.g. a streaming network reader). Non-negative int otherwise — no NaN, no negative, no `PHP_INT_MAX` sentinel.

---

## 7. Resume semantics (FR-037, FR-038)

### 7.1 Per-record commit (default)

The runner commits a `migration_run_state` row + id-map row + entity-storage rows in one DBAL transaction per record. `import:resume` reads `MAX(position) WHERE migration_id = ? AND run_id = ?` and asks the source plugin to iterate from that record forward.

### 7.2 Batched commit (opt-in, ≤100 records)

`--batch-size=N` (with `N ≤ 100`) wraps `N` records in one transaction. Resume on batch boundary granularity (positions are monotonic; the highest committed `position` is the resume point — partial batches roll back).

### 7.3 Source-plugin contract for resume

Source plugins MUST be re-entrant — calling `records()` twice on the same plugin MUST yield the same records in the same order. The runner skips records whose `source_id_hash` already has a `migration_run_state` row with `item_status === 'success'`. Plugins MAY optimize re-entry (e.g. seek to byte offset) but are not required to.

---

## 8. Rollback semantics (FR-043, FR-044)

`import:rollback <migration-id>` walks the id-map in reverse-creation order:

```sql
SELECT * FROM migration_id_map
WHERE migration_id = ?
ORDER BY last_imported_at DESC, last_run_id DESC
```

For each row, call `$destination->rollback($writeResult)`. Per-record exceptions are logged on `entity.lifecycle` and the walk continues (FR-044). After completion, `import:status` reflects per-record rollback success/failure.

Cross-migration rollback ordering is operator concern (Complexity Tracking #4 in plan.md) — `import:rollback` is per-migration.

---

## 9. Concurrency lock (FR-061, FR-062)

Lock file: `storage/migration-locks/<migration-id>.lock` containing the holding PID. `FilesystemLock`:

- `acquire()` writes the PID via `flock(LOCK_EX | LOCK_NB)`. If acquisition fails (another process holds the lock), read the PID from the file and raise `MigrationConcurrencyException` carrying `(lockPath: ..., pid: ...)`.
- `release()` closes the handle and unlinks the file. Called on normal exit.
- Signal handler (`pcntl_signal(SIGTERM/SIGINT, ...)` where `pcntl` extension is available) calls `release()` then re-raises (FR-062).
- Stale lock (PID not running) is NOT auto-cleared. `import:run` documents the manual recovery path (delete `storage/migration-locks/<migration-id>.lock` after verifying the PID is dead).

`MigrationConcurrencyException` has stable `code: 'migration_concurrent_run'`.

---

## 10. Error model (FR-045, FR-046, FR-047, FR-048)

Two error tiers:

### 10.1 Per-record errors

Captured in `migration_run_state.error_code` + `error_message`. Default behavior: log and continue. `--halt-on-error` flag (FR-047) halts on first per-record error.

Per-record errors are typed:
- `SourceReadException` — source plugin raised during `records()` or `sourceIdFor()`.
- `ProcessException` — process plugin transform raised.
- `DestinationWriteException` — destination write raised (including access denial).

### 10.2 Run-level errors

Always halt regardless of `--halt-on-error` (FR-048). These are framework-level:
- `MigrationConcurrencyException` — another run is in progress.
- `MigrationCycleException` — DAG cycle detected (boot-time, but re-validated on `import:run-all`).
- `MigrationDependencyMissingException` — dependency missing at run time.
- `MigrationAbortedException` — error-rate halt threshold crossed (FR-048; Q5 resolution).

`MigrationAbortedException` carries `code: 'migration_error_rate_halt'` plus `(errorRate: float, threshold: float, processed: int, errors: int)`.

---

## 11. Layering check

`packages/migration/` is **Layer 3 (Services)** per the CLAUDE.md layer table.

### 11.1 Imports (downward only)

- **Layer 0 (Foundation):** `Waaseyaa\Foundation\Log\LoggerInterface`, `Waaseyaa\Foundation\Database\DBALDatabase`, `Waaseyaa\Foundation\Event\*` (provider discovery), `Waaseyaa\Foundation\Migration\*` (schema migration system), `Waaseyaa\Validation\*` (used by `MigrationDefinition` constructor validators).
- **Layer 1 (Core Data):** `Waaseyaa\Entity\*`, `Waaseyaa\EntityStorage\*` (coordinator, `SaveContext`, lifecycle events from M-001), `Waaseyaa\Access\Gate\Gate`, `Waaseyaa\Access\AccountInterface`.

### 11.2 No upward imports

`packages/migration/` does NOT import from Layer 4 (api, routing), Layer 5 (ai-*), or Layer 6 (cli, admin-surface, graphql). The Layer 6 `packages/cli/` extends downward by importing from `packages/migration/` for the `import:*` commands — that's a Layer-6-imports-Layer-3 edge, which is allowed.

### 11.3 Validation

`bin/check-package-layers` validates every internal `waaseyaa/*` import edge at WP01 merge. Adding `packages/migration/composer.json` adds a new row to that script's coverage; no existing row breaks.

### 11.4 `require-dev` upward edges (allowed, warn-only)

`packages/migration/composer.json` will `require-dev` test fixtures from higher layers if needed (e.g. a node entity for round-trip testing). These appear in `bin/audit-require-dev-layers` as warn-only entries — acceptable per the layer table doctrine.

---

## 12. Charter anchors

Every stable-surface symbol traces back to a specific charter §5.x section. The mission proposes a new §5.8 "Migration platform" listing the §5.8-anchored symbols below; existing §5.3 and §4.4 anchors absorb the rest.

| Symbol | Charter anchor |
|---|---|
| `SourcePluginInterface`, `ProcessPluginInterface`, `DestinationPluginInterface` | §5.8 (new) |
| `HasMigrationPluginsInterface`, `HasMigrationsInterface` | §5.8 (new) |
| `MigrationDefinition` | §5.8 (new) |
| `EntityDestination` | §5.8 (new) |
| All six reserved-id process plugins (`PassThroughProcessor` etc.) | §5.8 (new) |
| `SourceId`, `SourceRecord`, `DestinationRecord`, `WriteResult`, `ProcessContext` | §5.8 (new) |
| `migration_id_map` table schema | §5.8 (new) |
| All eight exception classes | §5.8 (new) |
| `SaveContext::isImport()` method | §5.3 (entity surface; additive extension to M-001's surface) |
| `bin/waaseyaa import:run/run-all/status/rollback/reset/resume` | §5.8 (new) |
| `SourceConformanceTestCase`, `DestinationConformanceTestCase` | §5.8 (new) |
| `migration.deprecation` log channel | §4.4 (channels surface) |
| `migration_run_state` table schema | **NOT charter-listed** — mission-internal infrastructure (see §4.2 above) |

WP12 lands the §5.8 amendment plus `public-surface-map.md` / `public-surface-map.php` entries with `tier: stable, status: present` for every §5.8 row.

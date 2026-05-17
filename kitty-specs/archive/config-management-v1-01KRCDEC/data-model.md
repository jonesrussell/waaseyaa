# Data Model: Configuration Management v1

**Phase:** 1 (design)
**Mission:** M-003 / `config-management-v1-01KRCDEC`
**Date:** 2026-05-16

All types live under `declare(strict_types=1)`. `final readonly class` is the default for value objects; `final class` for services. PHP 8.5+ syntax; constructor property promotion preferred. Intersection types where contracts compose.

---

## Config package — interfaces

### `Waaseyaa\Config\Dependency\ConfigDependencyInterface`

```php
namespace Waaseyaa\Config\Dependency;

interface ConfigDependencyInterface
{
    /**
     * Declare the config entities this entity depends on.
     *
     * @return list<string> Each entry is a `<entity_type>.<entity_id>` reference
     *                     to another config entity that must exist before this one.
     */
    public function configDependencies(): array;
}
```

**Stability:** charter §5.5 stable surface from v0.x.

**Default implementation:** `Waaseyaa\Config\ConfigEntityBase` ships a default returning `[]`. Entity types declaring dependencies override.

---

### `Waaseyaa\Config\Sync\ConfigSyncRepositoryInterface`

```php
namespace Waaseyaa\Config\Sync;

interface ConfigSyncRepositoryInterface
{
    /** Enumerate every sync file in the store. */
    public function list(): iterable;       // iterable<ConfigSyncFile>

    /** Read a single sync file by `<entity_type>.<entity_id>` reference. */
    public function get(string $ref): ?ConfigSyncFile;

    /** Write a sync file, overwriting if present. Atomic via temp-then-rename. */
    public function put(ConfigSyncFile $file): void;

    /** Delete a single sync file by reference. */
    public function delete(string $ref): void;

    /** True if a sync file with this reference exists. */
    public function has(string $ref): bool;

    /** Absolute filesystem path the repository writes to (for diagnostics). */
    public function syncPath(): string;
}
```

**Stability:** INTERNAL. The interface exists so tests can substitute in-memory implementations; production uses the concrete `ConfigSyncRepository` only.

---

## Config package — value objects

### `Waaseyaa\Config\Sync\ConfigSyncFile`

```php
namespace Waaseyaa\Config\Sync;

final readonly class ConfigSyncFile
{
    /**
     * @param non-empty-string         $entityType
     * @param non-empty-string         $entityId
     * @param non-empty-string         $uuid
     * @param list<string>             $dependencies
     * @param non-empty-string         $langcode
     * @param array<string, mixed>     $fields    Alphabetically-sorted field values
     */
    public function __construct(
        public string $entityType,
        public string $entityId,
        public string $uuid,
        public array  $dependencies,
        public string $langcode,
        public array  $fields,
    ) {
        $this->validateShallow();
    }

    /** `<entity_type>.<entity_id>` canonical reference. */
    public function ref(): string;

    /** Expected filename: `<entity_type>.<entity_id>.yml`. */
    public function filename(): string;

    /** Deterministic content hash (SHA-256 of canonical YAML). */
    public function contentHash(): string;

    /** Shallow construction-time invariants. */
    private function validateShallow(): void;
}
```

**Construction-time invariants:**
- `entityType` and `entityId` match `/^[a-z][a-z0-9_]*$/`
- `uuid` non-empty
- `langcode` non-empty (defaults to `en` when constructed from active store and entity is non-translatable)
- `fields` keys are sorted alphabetically (asserted; not silently re-sorted)
- `dependencies` entries match `/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/`

**Stability:** the YAML representation is on stable surface (charter §5.5); the PHP class shape is INTERNAL (additive evolution permitted).

---

### `Waaseyaa\Config\Sync\ConfigManifestEntry`

```php
final readonly class ConfigManifestEntry
{
    public function __construct(
        public string $ref,              // <entity_type>.<entity_id>
        public string $entityType,
        public string $entityId,
        public string $uuid,
        public string $path,             // absolute filesystem path to the YAML file
        public string $contentHash,      // SHA-256 of canonical YAML content
        public int    $mtime,
    ) {}
}
```

Used by `ConfigStatusReporter` and `ConfigDiffer` for set-diff computations without round-trip-parsing every file.

---

### `Waaseyaa\Config\Dependency\DependencyGraph`

```php
namespace Waaseyaa\Config\Dependency;

final readonly class DependencyGraph
{
    /**
     * @param array<string, list<string>> $edges       node → outgoing edges
     * @param list<string>                $topologicalOrder
     */
    public function __construct(
        public array $edges,
        public array $topologicalOrder,
    ) {}

    public function nodes(): array;          // list<string>
    public function hasNode(string $ref): bool;
    public function edgesFrom(string $ref): array;   // list<string>
    public function isAcyclic(): bool;       // by construction, always true if constructed via DependencyResolver
}
```

**Constructor invariant:** `topologicalOrder` is a complete, acyclic ordering of the keys of `edges`. The constructor enforces this — passing an inconsistent order throws `InvalidArgumentException`.

**Stability:** INTERNAL. Consumers use `DependencyResolver::resolve()` to obtain a graph.

---

### `Waaseyaa\Config\Sync\DiffResult`

```php
final readonly class DiffResult
{
    public const STATUS_IN_SYNC    = 'in_sync';
    public const STATUS_DRIFT      = 'drift';
    public const STATUS_SYNC_ONLY  = 'sync_only';   // exists in sync, not in active
    public const STATUS_ACTIVE_ONLY = 'active_only'; // exists in active, not in sync
    public const STATUS_RENAMED   = 'renamed';

    /**
     * @param self::STATUS_*           $status
     * @param ?ConfigSyncFile          $syncSide
     * @param ?ConfigSyncFile          $activeSide   active store serialized into ConfigSyncFile shape for diff
     * @param ?string                  $renamedFrom  populated when $status === self::STATUS_RENAMED
     * @param string                   $unifiedDiff
     */
    public function __construct(
        public string $ref,
        public string $status,
        public ?ConfigSyncFile $syncSide,
        public ?ConfigSyncFile $activeSide,
        public ?string $renamedFrom,
        public string $unifiedDiff,
    ) {}
}
```

---

### `Waaseyaa\Config\Sync\StatusReport`

```php
final readonly class StatusReport
{
    /**
     * @param list<string> $inSync       refs in both stores with identical content
     * @param list<string> $drift        refs in both stores with differing content
     * @param list<string> $syncOnly     refs only in sync store
     * @param list<string> $activeOnly   refs only in active store (orphans)
     * @param list<string> $renamed      refs detected as renames (UUID match, id differs)
     */
    public function __construct(
        public array $inSync,
        public array $drift,
        public array $syncOnly,
        public array $activeOnly,
        public array $renamed,
    ) {}

    public function counts(): array;
    public function isInSync(): bool;
}
```

---

### `Waaseyaa\Config\Sync\ImportResult`

```php
final readonly class ImportResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $deleted,
        public int $failed,
        public int $unchanged,
        public array $perEntityFailures,    // array<string, \Throwable>  ref → exception
        public array $perEntityWarnings,    // array<string, string>      ref → reason
    ) {}

    public function isSuccess(): bool;       // failed === 0
    public function summaryLine(): string;   // "N created, M updated, K deleted, J failed, P unchanged."
}
```

---

### `Waaseyaa\Config\Audit\ConfigAuditEvent`

```php
namespace Waaseyaa\Config\Audit;

final readonly class ConfigAuditEvent
{
    public const OP_EXPORT = 'export';
    public const OP_IMPORT = 'import';
    public const OP_RESET  = 'reset';

    public function __construct(
        public string $operation,                  // self::OP_*
        public ?string $actor,                     // userId, CLI invoker, or null for system
        public ?string $entityRef,                 // populated for op=reset and per-entity import events
        public ?string $beforeAfterDigest,         // SHA-256 of "before:after" for tamper-evidence
        public int    $timestamp,
        public array  $context,                    // arbitrary structured context (file counts, dry-run flag, etc.)
    ) {}
}
```

Logged via `LoggerInterface::info()` on the `config.audit` channel (constant: `ConfigAuditChannel::CHANNEL = 'config.audit'`).

---

## Config package — services

### `Waaseyaa\Config\Dependency\DependencyResolver`

```php
final class DependencyResolver
{
    /**
     * Build the DAG from sync-store entries.
     *
     * @param list<ConfigSyncFile> $files
     * @return DependencyGraph
     * @throws ConfigDependencyCycleException
     * @throws ConfigDependencyMissingException
     */
    public function resolve(array $files): DependencyGraph;
}
```

**Algorithm:** DFS with white/gray/black coloring; on revisit of a gray node, raise `ConfigDependencyCycleException` with the reconstructed path. Missing-dep raised when an edge points to a ref not present in `$files` AND not present in the active store. Tie-break in topological order: lexicographic on ref string.

---

### `Waaseyaa\Config\Sync\ConfigSyncSerializer`

```php
final class ConfigSyncSerializer
{
    public function __construct(
        private readonly FieldValueMapper $fieldMapper,
        private readonly EntityTypeManager $entityTypes,
    ) {}

    /**
     * Serialize an active-store config entity into a sync-file value object.
     */
    public function serialize(ConfigEntityInterface $entity): ConfigSyncFile;

    /**
     * Emit canonical YAML for a sync file.
     * Sorted keys, `_meta` first, block-style except for empty arrays/maps.
     */
    public function toYaml(ConfigSyncFile $file): string;
}
```

**Yaml emitter options (pinned for determinism):**
- Block style for non-empty collections; flow style for empty (`[]`, `{}`).
- Keys sorted alphabetically within `_meta` and within each top-level field group.
- `_meta` block emitted first.
- Multi-line strings as YAML block scalars (`|` for literal, `>` for folded — chosen per content).

---

### `Waaseyaa\Config\Sync\ConfigSyncDeserializer`

```php
final class ConfigSyncDeserializer
{
    public function __construct(
        private readonly FieldValueMapper $fieldMapper,
        private readonly EntityTypeManager $entityTypes,
        private readonly ConfigFactoryInterface $factory,
    ) {}

    /**
     * Parse a YAML string into a ConfigSyncFile.
     *
     * @throws ConfigSerializationException on shape mismatch (e.g. _meta.entity_type ≠ filename prefix).
     */
    public function fromYaml(string $yaml, string $filename): ConfigSyncFile;

    /**
     * Materialize an active-store entity (un-persisted) from a sync file
     * for use by validators / would-be-import-preview.
     */
    public function toEntity(ConfigSyncFile $file): ConfigEntityInterface;
}
```

---

### `Waaseyaa\Config\Sync\FieldValueMapper`

```php
final class FieldValueMapper
{
    /**
     * Map an entity field value to its YAML representation per the table in spec §5.3.
     */
    public function toYamlValue(FieldDefinition $field, mixed $entityValue): mixed;

    /**
     * Inverse: map a parsed YAML value back to an entity field value.
     *
     * @throws ConfigSerializationException on type mismatch (e.g. YAML int where field expects datetime).
     */
    public function fromYamlValue(FieldDefinition $field, mixed $yamlValue): mixed;
}
```

**Type table (initial; FR-011a):**

| `FieldDefinition` type | YAML scalar / structure |
|---|---|
| `string` | scalar string |
| `int` | scalar int |
| `bool` | scalar bool |
| `datetime` | ISO 8601 string |
| `json` | mapping / sequence (native YAML structure) |
| `text` | scalar string (block scalar when multi-line) |
| `uuid` | scalar string |
| `entity_reference` | `<entity_type>.<entity_id>` string |
| `field_list` | sequence of scalars |

---

### `Waaseyaa\Config\Sync\ConfigExporter`

```php
final class ConfigExporter
{
    public function __construct(
        private readonly EntityTypeManager $entityTypes,
        private readonly ConfigManagerInterface $configManager,    // active store
        private readonly ConfigSyncSerializer $serializer,
        private readonly ConfigSyncRepositoryInterface $syncRepo,
        private readonly LoggerInterface $auditLogger,            // config.audit channel
    ) {}

    /**
     * @param bool $diff       When true, only write files whose content differs.
     * @param bool $dryRun     When true, compute writes without filesystem effect.
     */
    public function export(bool $diff = false, bool $dryRun = false): ExportResult;
}
```

---

### `Waaseyaa\Config\Sync\ConfigImporter`

```php
final class ConfigImporter
{
    public function __construct(
        private readonly ConfigSyncRepositoryInterface $syncRepo,
        private readonly ConfigSyncDeserializer $deserializer,
        private readonly DependencyResolver $resolver,
        private readonly ConfigSyncValidator $validator,
        private readonly ConfigManagerInterface $configManager,
        private readonly DatabaseInterface $database,             // for per-entity transactions
        private readonly LoggerInterface $auditLogger,
    ) {}

    /**
     * @param bool $dryRun
     * @param bool $deleteOrphans       FR-026; default warn.
     * @param bool $haltOnError         FR-028; default continue.
     * @param bool $noDependencyCheck   FR-007; emergency bypass.
     */
    public function import(
        bool $dryRun = false,
        bool $deleteOrphans = false,
        bool $haltOnError = false,
        bool $noDependencyCheck = false,
    ): ImportResult;
}
```

---

### `Waaseyaa\Config\Sync\ConfigDiffer`

```php
final class ConfigDiffer
{
    /**
     * @param ?string $ref   If null, diff every entity in either store.
     * @return list<DiffResult>
     */
    public function diff(?string $ref = null): array;
}
```

Both sides serialize to canonical YAML before diffing — eliminates spurious whitespace / ordering differences. Unified-diff output via a vendor diff library (or hand-rolled — TBD at WP05 task time).

---

### `Waaseyaa\Config\Sync\ConfigStatusReporter`

```php
final class ConfigStatusReporter
{
    public function status(): StatusReport;
}
```

**Algorithm:** enumerate active-store refs (one query per entity type) and sync-store refs (filesystem scan); compute set diff per entity type; compute content-hash equality for entries present in both; detect renames via UUID equality + id difference.

---

### `Waaseyaa\Config\Sync\ConfigSyncValidator`

```php
final class ConfigSyncValidator
{
    public function __construct(
        private readonly ConfigSyncRepositoryInterface $syncRepo,
        private readonly ConfigSyncDeserializer $deserializer,
        private readonly EntityTypeManager $entityTypes,
    ) {}

    /**
     * Validate every sync file via FieldDefinition::validators().
     *
     * @return list<ValidationError>   Empty if every file is valid.
     */
    public function validate(): array;
}
```

Reuses ADR 013's validation pipeline; produces per-field, per-entity error reports. `ConfigImporter::import()` calls this first when `noDependencyCheck === false`.

---

### `Waaseyaa\Config\Sync\ConfigResetter`

```php
final class ConfigResetter
{
    public function reset(string $ref, bool $skipConfirmation = false): ResetResult;
}
```

Single-entity reset; transactional; logs to `config.audit`. Confirmation prompt is delegated to the calling CLI command (`ConfigResetCommand`) — the resetter itself never prompts; `$skipConfirmation` is informational for the audit log only.

---

### `Waaseyaa\Config\Backend\BackendRestrictionEnforcer`

```php
namespace Waaseyaa\Config\Backend;

final class BackendRestrictionEnforcer
{
    public const ALLOWED_BACKENDS = ['sql-blob', 'sql-column'];

    /**
     * Boot-time check: every registered config entity type uses an allowed backend.
     *
     * @throws InvalidConfigBackendException
     */
    public function assertCompliant(EntityTypeManager $entityTypes): void;
}
```

Wired into `StorageBackendRegistry::register()` or invoked once at kernel boot post entity-type registration.

---

## CLI package — command surface

### `Waaseyaa\Cli\Command\Config\ConfigCommand` (abstract base)

```php
namespace Waaseyaa\Cli\Command\Config;

abstract class ConfigCommand extends Command
{
    public const RESERVED_VERBS = [
        'config:export',
        'config:import',
        'config:diff',
        'config:status',
        'config:validate',
        'config:reset',
    ];
}
```

`CliKernel` boot-time hook reads `RESERVED_VERBS` and refuses to start if any app-registered command name collides with a verb NOT bound to a `ConfigCommand` subclass owned by `packages/cli`.

---

## Exception hierarchy

All inherit from `Waaseyaa\Config\Exception\ConfigExceptionInterface` (intersection: `\Throwable`):

```php
namespace Waaseyaa\Config\Dependency\Exception;

final class ConfigDependencyCycleException extends \RuntimeException
{
    public function __construct(
        public readonly array $cyclePath,    // list<string>; the full cycle
        public readonly string $code = 'config.dependency.cycle',
        ?\Throwable $previous = null,
    );

    public function getCycle(): array;
}

final class ConfigDependencyMissingException extends \RuntimeException
{
    public function __construct(
        public readonly string $missingRef,
        public readonly string $requiredBy,
        public readonly string $code = 'config.dependency.missing',
        ?\Throwable $previous = null,
    );
}
```

```php
namespace Waaseyaa\Config\Exception;

final class InvalidConfigBackendException extends \LogicException
{
    public function __construct(
        public readonly string $entityTypeId,
        public readonly string $backendId,
        public readonly string $declaringFqcn,
        public readonly string $code = 'config.backend.invalid',
    );
}

final class ConfigSerializationException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $filename,
        public readonly string $code = 'config.sync.serialization',
        ?\Throwable $previous = null,
    );
}

final class ConfigImportFailedException extends \RuntimeException
{
    public function __construct(
        public readonly string $entityRef,
        string $message,
        public readonly string $code = 'config.import.failed',
        ?\Throwable $previous = null,
    );
}

final class ConfigCommandCollisionException extends \LogicException
{
    public function __construct(
        public readonly string $reservedVerb,
        public readonly string $offendingFqcn,
        public readonly string $code = 'config.cli.collision',
    );
}
```

---

## Sync-store on-disk layout (canonical)

```
storage/config-sync/
├── role.admin.yml
├── role.coordinator.yml
├── taxonomy_vocabulary.community_categories.yml
├── taxonomy_vocabulary.parent_thing.yml
└── menu.main_navigation.yml
```

Filename = `<entity_type>.<entity_id>.yml`. Files with non-matching `_meta.entity_type` raise `ConfigSerializationException` on parse. Files outside the naming convention are warn-skipped at import (FR-009 / §5.1).

---

## Open data-model questions for WP-detail phase

- Exact column types in the per-entity-failure map of `ImportResult::$perEntityFailures` — `\Throwable` vs. a sealed `ImportFailureKind` enum.
- Whether `ConfigSyncRepository` should expose a streaming iterator (`Generator<ConfigSyncFile>`) for large stores instead of `iterable` over array.
- Whether `DiffResult::$unifiedDiff` should be lazily materialized (cost concern on `config:status` over many entities — diff only computed when explicitly requested).
- Confirmation-prompt interface — does `ConfigResetter` take a `ConfirmationPromptInterface` for testability, or does the CLI layer own the prompt entirely?

These are interface-detail decisions captured per WP at the implement phase; the present `data-model.md` captures the shapes that consumers will see.

# Contract: Active / Sync Store Split

**Stability scope:** charter §5.5 (amended at mission close)
**FRs covered:** FR-009..FR-016, FR-022..FR-029, FR-044..FR-046
**Owned by:** WP02 (sync repository + file format), WP04 (importer), WP08 (backend restriction)

## Purpose

The framework keeps a **dual-storage model** for config entities:

1. The **active store** (DB) is the runtime state. All reads/writes during a request happen against this store. No change from current behaviour.
2. The **sync store** (filesystem, YAML) is the deploy artifact. CI exports active → sync as part of a release cut; CD imports sync → active during deploy. The sync store is the only canonical form that lives in the consumer app's git history.

Round-trip equality is the central invariant: `import(export(active)) === active`.

## Stability commitments

- The **default sync path** (`storage/config-sync/`) is on stable surface. Future framework changes do NOT relocate it without a charter §4 deprecation cycle.
- The **`config.sync_path`** config key is on stable surface. Consumers may override.
- The **per-file naming convention** (`<entity_type>.<entity_id>.yml`) is on stable surface. Future format additions (e.g. multi-langcode files) require a new naming convention, not a rename of the existing one.
- The **`_meta` block schema** (entity_type, uuid, dependencies, langcode) is on stable surface. New `_meta` keys require charter §4 deprecation cycle. Existing keys cannot be removed without a major-version break.
- The **filename ↔ `_meta.entity_type` correspondence** is a hard invariant; mismatch is `ConfigSerializationException`.

## Active store interface (existing; documented for completeness)

The active store is already shipped via `Waaseyaa\Config\Storage\StorageInterface` + `ConfigManagerInterface` + `ConfigFactoryInterface`. CMI consumes the existing surface; it does NOT replace or wrap it.

```php
namespace Waaseyaa\Config;

interface ConfigManagerInterface
{
    public function loadEntity(string $entityType, string $id): ?ConfigEntityInterface;
    public function listEntitiesOfType(string $entityType): iterable;
    public function listAllEntityTypes(): array;     // list<string>
    public function saveEntity(ConfigEntityInterface $entity): void;
    public function deleteEntity(ConfigEntityInterface $entity): void;
}
```

CMI does not alter this interface. Whenever CMI needs to read or write the active store, it goes through `ConfigManagerInterface`.

## Sync store interface (NEW)

```php
namespace Waaseyaa\Config\Sync;

interface ConfigSyncRepositoryInterface
{
    /** Iterate every sync file currently on disk. Non-conforming files are warn-skipped. */
    public function list(): iterable;       // iterable<ConfigSyncFile>

    /** Read one sync file by reference (`<entity_type>.<entity_id>`). Returns null if absent. */
    public function get(string $ref): ?ConfigSyncFile;

    /** Write a sync file. Atomic via temp-then-rename (no partial writes visible). */
    public function put(ConfigSyncFile $file): void;

    /** Delete a single sync file. No-op if absent. */
    public function delete(string $ref): void;

    /** True if the named sync file exists on disk. */
    public function has(string $ref): bool;

    /** Absolute filesystem path (for diagnostics, status, and audit log context). */
    public function syncPath(): string;
}
```

**Default implementation:** `Waaseyaa\Config\Sync\ConfigSyncRepository` reads/writes under the configured path. Constructor injects the resolved `config.sync_path` value.

**Atomic write contract:** `put()` writes to `<filename>.tmp`, fsync, then renames. A crash mid-write never leaves a partially-written sync file in place. Confirmed by a unit test that injects a write failure between fsync and rename.

## Per-entity transaction contract

Every `ConfigImporter::import()` per-entity write happens in its **own database transaction**:

```php
foreach ($graph->topologicalOrder as $ref) {
    $database->transactional(function () use ($ref) {
        // deserialize sync file; save through ConfigManagerInterface
    });
}
```

- Success commits the per-entity transaction.
- Failure rolls back the per-entity transaction and records the error against `$ref`.
- `--halt-on-error` stops the loop after the first failure; default mode continues.

The **import as a whole is NOT atomic.** A run that fails on entity #50 of 200 leaves the first 49 applied to the active store. The cookbook documents this prominently with rollback recipes (re-run `config:import` after fix; partial state is operator-debuggable via `config:diff`).

## Backend restriction (FR-044..FR-046)

Config entities are restricted to two storage backends:

| Backend | Allowed for config entities? |
|---|---|
| `sql-blob` | YES |
| `sql-column` | YES |
| `vector` | NO |
| `remote` | NO |
| any future backend | default NO; explicit charter §4 amendment required to permit |

**Enforcement:** `BackendRestrictionEnforcer::assertCompliant()` runs at kernel boot, after entity-type registration, before any HTTP / CLI request is dispatched. Violations raise `InvalidConfigBackendException` with `(entity_type_id, backend_id, declaring_fqcn)` — kernel refuses to boot per charter §5.4.

This restriction is **typed**, not advisory; there is no escape hatch in v0.x. Consumers wanting vector-backed config entities must request a charter amendment.

## UUID semantics

- Sync-file `_meta.uuid` MUST match the active-store entity's UUID.
- On first `config:export` for a legacy pre-CMI entity without a UUID, the framework generates a deterministic UUID `sha-256(entity_type + '.' + entity_id)` (truncated/reshaped to UUID v5 format) and writes it back to the active store. From that point on the UUID is stable.
- A sync file whose `_meta.uuid` matches an active-store entity but whose id differs is treated as a **rename**, not a delete+create. The `DiffResult::STATUS_RENAMED` status surfaces this. Import preserves history — same DB row, new id.

## Round-trip preservation (FR-055)

The mission's central contract test: `export → import` without modification produces zero observable change in the active store. No spurious writes, no `lastUpdatedAt` churn, no UUID regeneration. This is the proof that the sync layer is a faithful representation, not a lossy projection.

Verified at WP02 (round-trip serializer test) and again at WP10 (full Minoo round-trip).

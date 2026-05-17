# Contract — Revisionable Entity + Storage

**Owning WPs**: WP07 (schema + opt-in), WP08 (storage surface), WP09 (per-revision access).
**Source**: spec §3.6, §9, §11; ADR 016.
**Stable surface**: yes (charter §5.3).

---

## Entity interface

```php
namespace Waaseyaa\Entity;

/**
 * @api — marker for revisionable entities. Charter §5.3.
 *
 * Implementations are normally classes that already extend {@see ContentEntityBase}
 * plus this interface; the `RevisionableEntityTrait` provides the boilerplate.
 */
interface RevisionableEntityInterface extends EntityInterface
{
    public function revisionId(): int|string|null;
    public function isCurrentRevision(): bool;
    public function revisionMetadata(): RevisionMetadata;
}
```

## Storage interface

```php
namespace Waaseyaa\EntityStorage;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\RevisionableEntityInterface;

/**
 * @api
 *
 * Mixed into per-entity-type storage when the entity type is revisionable.
 * Non-revisionable entity types do NOT implement this.
 */
interface RevisionableEntityStorageInterface
{
    /** Load any historical revision by vid. Bypasses the current-revision pointer. */
    public function loadRevision(EntityTypeInterface $type, int|string $revisionId): ?RevisionableEntityInterface;

    /**
     * Iterate revisions in descending revision_created_at order. Pagination is consumer concern.
     *
     * @return iterable<RevisionableEntityInterface>
     */
    public function listRevisions(RevisionableEntityInterface $entity): iterable;

    /**
     * Re-point the primary table's vid to an existing revision. Dispatches Before/AfterSave events.
     */
    public function setCurrentRevision(RevisionableEntityInterface $entity, int|string $revisionId): void;
}
```

## SaveContext value object

```php
namespace Waaseyaa\EntityStorage;

/**
 * @api — flags for save operations. Extensible to future flags.
 */
final class SaveContext
{
    private function __construct(
        public readonly bool $withoutNewRevision = false,
    ) {}

    public static function default(): self { return new self(); }
    public function withoutNewRevision(): self { return new self(withoutNewRevision: true); }
}
```

## EntityType additions

`EntityType` gains two optional slots:

| Field | Type | Default | Notes |
|---|---|---|---|
| `revisionable` | `bool` | `false` | Opt-in per entity type. Required when `entityKeys.revision` is set. |
| `primaryStorageBackend` | `string` | `'sql-blob'` | Per-entity-type default backend; default keeps existing entity types unchanged during migration window. |

The `entityKeys` array gains a `'revision'` slot:

```php
new EntityType(
    id: 'teaching',
    keys: ['id' => 'tid', 'uuid' => 'uuid', 'revision' => 'vid'],
    revisionable: true,
    primaryStorageBackend: 'sql-column',
    ...
);
```

## Schema shape (sql-column)

Primary table:

```
<entity>(
    <id_column> PRIMARY KEY,
    uuid TEXT,
    bundle TEXT,
    langcode TEXT,
    vid INTEGER,  -- current-revision pointer
    -- field columns…
)
```

Revision table:

```
<entity>__revision(
    vid INTEGER PRIMARY KEY,
    <fk_column>,
    revision_created_at TEXT,
    revision_author INTEGER,
    revision_log TEXT,
    -- field columns (same shape as primary)…
)
```

## Save semantics

Per FR-032: every save on a revisionable entity creates a new revision unless `SaveContext::withoutNewRevision()` is set. New revision row is inserted; the primary row's `vid` updates LAST. The operation is transactional within the primary backend; partial failure across backends raises `PartialSaveException`.

## Per-revision access (WP09)

`GateInterface` gains a `view_revision` op constant. Policies declare it via:

```php
#[PolicyAttribute(entityType: 'teaching', operations: ['view', 'edit', 'view_revision'])]
final class TeachingAccessPolicy {
    public function viewRevision(Teaching $entity, AccountInterface $account, Revision $revision): bool { ... }
}
```

Fallback: policies that do NOT declare `view_revision` fall back to `view`. Framework MUST NOT default-deny. A structured log line on `entity.lifecycle` fires when fallback applies.

## RevisionPruner (WP08, disabled by default)

```php
namespace Waaseyaa\EntityStorage;

/**
 * @api — ships disabled. Apps configure; framework does not invoke.
 */
final class RevisionPruner
{
    public function __construct(private readonly bool $enabled = false, ...) {}
    public function prune(EntityTypeInterface $type, RevisionPruningPolicy $policy): RevisionPruningReport { ... }
}
```

## Test surface

- Revision integration tests under `tests/Integration/Revisions/`: round-trip save → loadRevision → listRevisions → setCurrentRevision.
- Per-revision access tests under `packages/access/tests/`: fallback when undeclared, custom rule when declared.
- WP11 validation: real `teaching` revision flow in Minoo.

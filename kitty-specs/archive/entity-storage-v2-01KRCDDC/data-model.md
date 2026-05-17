# Data Model — Entity Storage v2

This is the structural map for the substrate the mission delivers. Every symbol here is derived from `docs/specs/entity-storage-v2.md` (normative) and ADRs 010, 011, 016. Symbol names follow the spec; nothing here is invented.

---

## 1. Stable-surface symbols (per spec §4)

All symbols below are governed by charter §5.3 (stable tier). They are part of the public contract once shipped.

### 1.1 Interfaces

| Symbol | Layer | Governing ADR | Purpose |
|---|---|---|---|
| `FieldStorageBackendInterface` | `packages/entity-storage/src/Backend/` (Layer 1) | 010 | Single per-field storage strategy: read/write/delete/supportsQuery. |
| `HasFieldStorageBackendsInterface` | same | 010 | Provider capability marker; surfaces backends through Composer discovery. |
| `RevisionableEntityInterface` | `packages/entity/src/` (Layer 1) | 016 | Opt-in marker on entity classes that carry revisions. |
| `RevisionableEntityStorageInterface` | `packages/entity-storage/src/` | 016 | Storage surface: `loadRevision()`, `listRevisions()`, `setCurrentRevision()`. |
| `EntityLifecycleEventInterface` | `packages/entity-storage/src/Event/` | 011 | Marker for all four lifecycle events. |

### 1.2 Classes (concrete, public)

| Symbol | Layer | Governing ADR | Purpose |
|---|---|---|---|
| `EntityStorageCoordinator` | `packages/entity-storage/src/` | 010+011 | Fan-out / fan-in across registered backends. Owns lifecycle event dispatch. |
| `SqlBlobBackend` | `packages/entity-storage/src/Backend/` | 010 | Refactor of current `_data` JSON path. Behavior-identical. |
| `SqlColumnBackend` | `packages/entity-storage/src/Backend/` | 010 | New: real columns + indexes per `FieldDefinition`. |
| `BeforeSaveEvent` | `packages/entity-storage/src/Event/` | 011 | Dispatched before any backend write. |
| `AfterSaveEvent` | same | 011 | Dispatched after all backend writes succeed. |
| `BeforeDeleteEvent` | same | 011 | Dispatched before any backend delete. |
| `AfterDeleteEvent` | same | 011 | Dispatched after all backend deletes succeed. |
| `SaveContext` | `packages/entity-storage/src/` | 016 | Value object — flags for save operations (`withoutNewRevision()`, future-extensible). |
| `RevisionPruner` | `packages/entity-storage/src/` | 016 | Disabled by default. Configurable per-app; not used by framework. |

### 1.3 Exceptions

| Symbol | Layer | Governing ADR | Purpose |
|---|---|---|---|
| `PartialSaveException` | `packages/entity-storage/src/Exception/` | 010 | Coordinator fan-out partial failure. Carries `committedBackends` + `uncommittedBackends`. |
| `UnsupportedQueryException` | same | 010 | Raised at definition-validation time when a backend cannot satisfy declared query/index needs. |
| `UnsupportedListingException` | same | 015 (forward-compat) | Listing-builder integration; reserved here. |
| `BackendIdCollisionException` | same | 010 | Two backends register the same id at boot. |
| `AbortOperationException` | `packages/entity-storage/src/Event/` | 011 | Thrown from a `BeforeSave`/`BeforeDelete` subscriber to halt the operation. |

### 1.4 Constants / op tokens

| Symbol | Purpose | Reference |
|---|---|---|
| Reserved backend ids `sql-blob`, `sql-column`, `vector` | Framework-owned id namespace; collision throws at boot. | spec §5.2 |
| `view_revision` op token in `GateInterface` | Per-revision access operation. | spec §11 |
| `entity.lifecycle` log channel | Structured log channel for lifecycle events. | spec §4 |

### 1.5 CLI

| Command | Purpose | Reference |
|---|---|---|
| `bin/waaseyaa make:storage-migration <entity_type>` | Generates a per-type migration from `sql-blob` to `sql-column`. Rides existing `bin/waaseyaa migrate` system (ADR 009). | spec §10 |

---

## 2. Entity model

### 2.1 Entity-type definition shape (additive)

`EntityType` gains two optional slots:

```php
new EntityType(
    id: 'teaching',
    label: 'Teaching',
    class: Teaching::class,
    keys: [
        'id' => 'tid',
        'uuid' => 'uuid',
        'revision' => 'vid',          // new — required when revisionable
    ],
    revisionable: true,                // new — opt-in flag
    primaryStorageBackend: 'sql-column', // new — default 'sql-blob' during migration
    fieldDefinitions: [
        FieldDefinition::create('title', 'string')->indexed(),
        FieldDefinition::create('body', 'text'),
        FieldDefinition::create('embedding', 'float_vector_768')
            ->storedIn('vector'),       // per-field backend override
    ],
);
```

Backward-compatibility rules:

- `revisionable` defaults to `false`. Existing entity types are unaffected.
- `primaryStorageBackend` defaults to `sql-blob` during the migration window so unmodified entity types continue to persist exactly as today.
- `entityKeys.revision` is required iff `revisionable: true`.
- `FieldDefinition::storedIn()` is opt-in per field; fields without an override use the entity's primary backend.

### 2.2 Field-level storage routing

Routing precedence (highest first):

1. `FieldDefinition::storedIn(<id>)` — explicit per-field override.
2. `EntityType::$primaryStorageBackend` — per-entity-type default.
3. Framework default — `sql-blob`.

The coordinator groups fields by resolved backend before dispatching reads/writes. Order across backends is implementation-defined for reads; for writes, the entity's primary backend writes first, alternates second in registration order (spec §6.2).

---

## 3. Storage shape

### 3.1 `sql-blob` (refactor of current `_data` path)

Identical to today. Primary row carries entity keys + a `_data` TEXT column with JSON-encoded field values. Index strategy is unchanged. For revisionable types, a `_data`-shaped revision table is added:

```
<entity_table>__revision(
    vid INTEGER PRIMARY KEY,
    <id_column>,
    revision_created_at,
    revision_author,
    revision_log,
    _data TEXT  -- JSON-encoded snapshot
)
```

### 3.2 `sql-column` (new)

Primary row carries entity keys + one column per `FieldDefinition`. Indexes are declared via `FieldDefinition::indexed()` and materialize as B-tree indexes. Revision table mirrors the primary row's column layout (spec §9.1).

Example (`teaching` entity):

```
teaching(
    tid INTEGER PRIMARY KEY,
    uuid TEXT,
    bundle TEXT,
    langcode TEXT,
    vid INTEGER,             -- current-revision pointer
    title TEXT,              -- indexed via FieldDefinition::indexed()
    body TEXT,
    published_at TEXT,       -- ISO 8601 (SQLite) / TIMESTAMPTZ (Postgres)
    community_id INTEGER,    -- entity_reference, indexed
    -- …other field columns
)

teaching__revision(
    vid INTEGER PRIMARY KEY,
    tid INTEGER,             -- FK to teaching.tid
    revision_created_at TEXT,
    revision_author INTEGER,
    revision_log TEXT,
    title TEXT,
    body TEXT,
    published_at TEXT,
    community_id INTEGER,
    -- …same field columns
)
```

### 3.3 Type mapping (spec §8.2)

| `FieldDefinition` type | SQLite | Postgres |
|---|---|---|
| `string` | `TEXT` | `TEXT` (or `VARCHAR(n)` when length declared) |
| `int` | `INTEGER` | `INTEGER` |
| `bigint` | `INTEGER` | `BIGINT` |
| `bool` | `INTEGER` (0/1) | `BOOLEAN` |
| `datetime` | `TEXT` (ISO 8601) | `TIMESTAMPTZ` |
| `json` | `TEXT` | `JSONB` |
| `uuid` | `TEXT` | `UUID` |
| `text` | `TEXT` | `TEXT` |
| `float` | `REAL` | `DOUBLE PRECISION` |
| `decimal` | `TEXT` (lossless decimal string) | `NUMERIC(p, s)` |
| `float_vector_<n>` | (forbidden — route to `vector` backend) | (forbidden — route to `vector` backend) |

---

## 4. Lifecycle semantics

```
save(entity, SaveContext?):
    dispatch BeforeSaveEvent
        if AbortOperationException → halt, propagate
    if revisionable and not SaveContext::withoutNewRevision():
        insert new revision row
    group fields by backend
    write primary-backend fields
    write alternate-backend fields in registration order
    if any backend write fails:
        raise PartialSaveException(committed=[…], uncommitted=[…])
        AfterSaveEvent does NOT fire
    update primary table's current-revision pointer (vid) last
    dispatch AfterSaveEvent
```

```
delete(entity):
    dispatch BeforeDeleteEvent
        if AbortOperationException → halt, propagate
    group fields by backend
    delete primary-backend rows
    delete alternate-backend rows in registration order
    if any backend delete fails:
        raise PartialSaveException(…)
        AfterDeleteEvent does NOT fire
    dispatch AfterDeleteEvent
```

```
load(entityType, id):
    resolve primary backend
    for each field, resolve backend (override or primary)
    group fields by backend
    for each backend, call read(entity, field) for that backend's fields
    assemble entity instance from results
```

---

## 5. Revision semantics

| Operation | Behavior |
|---|---|
| `save()` on a revisionable entity | Creates a new revision unless `SaveContext::withoutNewRevision()` is set. New revision row is inserted; primary table's `vid` updates last. Transactional. |
| `loadRevision($entityType, $revisionId)` | Loads any historical revision by `vid`. Bypasses current-revision pointer. |
| `listRevisions($entity)` | Returns iterable in descending `revision_created_at` order. Pagination is consumer-side. |
| `setCurrentRevision($entity, $revisionId)` | Re-points the primary table's `vid` to an existing revision. Dispatches lifecycle events. |
| `RevisionPruner` | Class ships disabled. Apps may configure; framework does not invoke. |

---

## 6. Access semantics

### 6.1 Operation tokens

| Op | Default policy | Notes |
|---|---|---|
| `view` | per-entity | Existing op. |
| `edit` | per-entity | Existing op. |
| `delete` | per-entity | Existing op. |
| `view_revision` | falls back to `view` when undeclared | NEW. Distinct op for historical revisions. |

### 6.2 Policy declaration shape

```php
#[PolicyAttribute(entityType: 'teaching', operations: ['view', 'edit', 'view_revision'])]
final class TeachingAccessPolicy
{
    public function view(Teaching $entity, AccountInterface $account): bool { … }
    public function edit(Teaching $entity, AccountInterface $account): bool { … }
    public function viewRevision(Teaching $entity, AccountInterface $account, Revision $revision): bool { … }
}
```

### 6.3 Fallback rule

Policies that do NOT declare `view_revision` fall back to `view`. Framework MUST NOT default-deny (silent denial would break existing policies on first deploy after this mission ships).

---

## 7. Relationships and dependencies

### 7.1 Internal package layering

| Package | Layer | Role |
|---|---|---|
| `waaseyaa/entity-storage` | 1 (Core Data) | Owns coordinator, backends, lifecycle events, revision storage, migration generator. |
| `waaseyaa/entity` | 1 (Core Data) | Owns entity classes, `RevisionableEntityInterface`, `EntityType`, `FieldDefinition`. |
| `waaseyaa/field` | 1 (Core Data) | Owns field-type definitions consumed by type mapping. |
| `waaseyaa/access` | 1 (Core Data) | Owns `GateInterface` + policy attribute; gains `view_revision` op. |
| `waaseyaa/cli` | 6 (Interfaces) | Hosts `bin/waaseyaa make:storage-migration` command implementation. |

Layer rule check: every new edge is intra-Layer-1 except the CLI command, which is Layer 6 reaching down — allowed. No upward edges introduced.

### 7.2 Downstream consumers (per `mission.json`)

| Consumer | Relationship |
|---|---|
| Minoo (validation app) | WP11 migrates `teaching` end-to-end. |
| M-002 (migration platform v1) | Inherits the migration manifest path established by WP10. |
| M-004 (translatable revisions) | Builds directly on revision substrate. |

### 7.3 Charter anchors

| Stable-surface entry | Charter anchor |
|---|---|
| Backend contract + reserved id namespace | §5.3 "Field storage backend contract" |
| `FieldDefinition::storedIn()`, `FieldDefinition::indexed()` | §5.3 "FieldDefinition API" |
| `EntityType.revisionable`, `EntityType.entityKeys.revision` | §5.3 "EntityType definition shape" |
| Lifecycle events + `AbortOperationException` | §5.3 "Entity lifecycle events" |
| `RevisionableEntityInterface`, `RevisionableEntityStorageInterface` | §5.3 "Revisionable surface" |
| `view_revision` op | §5.3 "Access-policy attribute system" |
| New exceptions | New §5.3 entries — to add during ratification of this mission. |
| `entity.lifecycle` log channel | §4.4 |
| `bin/waaseyaa make:storage-migration` | §5.2 (console surface) |

---

## 8. Open data-model questions (mirrors research §3)

| # | Question | Resolution target |
|---|---|---|
| Q1 | Backend registration order across packages | WP01 — Composer install order + optional `priority: int`. |
| Q6 | `SaveContext` shape | WP04 — dedicated value-object class. |

Other open questions (Q2–Q5) affect migration mechanics and pruning, not the data model itself.

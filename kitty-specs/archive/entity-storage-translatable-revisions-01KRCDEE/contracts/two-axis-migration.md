# Contract — Two-axis migration generator behavior

**Status:** Normative for WP06.
**Refs:** FR-025..FR-029; spec §3.5.

---

## 1. Scope

Defines the behavior of `bin/waaseyaa make:storage-migration <entity_type>` for two-axis promotion. Extends M-006's existing `AddTranslationsMigrationGenerator` and adds a new sibling generator for `--add-revisions`.

## 2. Input shape × output mapping

| Input entity type | Flag | Generator | Outcome |
|---|---|---|---|
| Non-translatable, non-revisionable | `--add-translations` | `AddTranslationsMigrationGenerator` (M-006) | M-006 single-axis path (unchanged) |
| Revisionable, non-translatable | `--add-translations` | `AddTranslationsMigrationGenerator` (extended) | **NEW: two-axis promotion path** |
| Translatable, non-revisionable | `--add-revisions` | `AddRevisionsMigrationGenerator` (NEW) | Two-axis promotion path |
| Already two-axis | `--add-translations` OR `--add-revisions` | (either) | Raises `StorageMigrationException::noOpPromotion($entityType)` (FR-029) |
| Non-translatable, non-revisionable | `--add-revisions` | `AddRevisionsMigrationGenerator` | Single-axis revisionable-only path (mirror of M-006 single-axis translations path) |

## 3. `AddTranslationsMigrationGenerator` (extension)

### 3.1 Detection branch

```php
public function generate(string $entityType): string
{
    $existing = $this->entityTypeManager->getDefinition($entityType);

    if ($existing->isTranslatable()) {
        // already translatable — either no-op or two-axis if also revisionable
        if ($existing->isRevisionable()) {
            throw StorageMigrationException::noOpPromotion($entityType);
        }
        throw StorageMigrationException::noOpPromotion($entityType);  // already single-axis translatable
    }

    if ($existing->isRevisionable()) {
        return $this->generateTwoAxisFromRevisionable($entityType);   // NEW path
    }

    return $this->generateSingleAxisTranslatable($entityType);        // M-006 unchanged
}
```

### 3.2 Two-axis-from-revisionable output

For an existing revisionable-only entity type `teaching` (with `teaching` and `teaching__revision` tables already populated):

```sql
-- Up migration
CREATE TABLE teaching__translation (
  tid INTEGER NOT NULL,
  langcode TEXT NOT NULL,
  vid INTEGER NOT NULL,
  PRIMARY KEY (tid, langcode)
);

CREATE TABLE teaching__translation__revision (
  vid INTEGER PRIMARY KEY,
  tid INTEGER NOT NULL,
  langcode TEXT NOT NULL,
  revision_created_at TEXT NOT NULL,
  revision_author INTEGER,
  revision_log TEXT,
  -- translatable field columns (per FieldDefinition::translatable() partition)
  title TEXT,
  body TEXT,
  UNIQUE (tid, langcode, vid)
);
CREATE INDEX idx_teaching_tx_rev_lookup ON teaching__translation__revision (tid, langcode, vid DESC);

-- Backfill: each existing teaching__revision row becomes a default-langcode translation-revision row.
INSERT INTO teaching__translation__revision (vid, tid, langcode, revision_created_at, revision_author, revision_log, title, body)
SELECT vid, tid, 'en', revision_created_at, revision_author, revision_log, title, body
FROM teaching__revision;

-- Backfill: set per-(entity, default_langcode) current-revision pointer.
INSERT INTO teaching__translation (tid, langcode, vid)
SELECT tid, default_langcode, vid FROM teaching;

-- (No change to teaching.vid or teaching__revision — the entity-level primary current-revision pointer remains.)
-- Remove translatable column from teaching__revision (e.g. title, body — they moved to teaching__translation__revision):
ALTER TABLE teaching__revision DROP COLUMN title;
ALTER TABLE teaching__revision DROP COLUMN body;
```

### 3.3 Reverse migration (FR-028)

```sql
-- Reverse: collapse two-axis back to revisionable-only.
-- DATA LOSS: non-default-langcode revisions are dropped (cannot be reconstructed in revisionable-only schema).

-- Re-add translatable columns to teaching__revision:
ALTER TABLE teaching__revision ADD COLUMN title TEXT;
ALTER TABLE teaching__revision ADD COLUMN body TEXT;

-- Copy default-langcode current-translation-revision values into teaching__revision:
UPDATE teaching__revision SET (title, body) = (
  SELECT title, body FROM teaching__translation__revision
  WHERE teaching__translation__revision.tid = teaching__revision.tid
    AND teaching__translation__revision.langcode = (SELECT default_langcode FROM teaching WHERE tid = teaching__revision.tid)
    AND teaching__translation__revision.vid = teaching__revision.vid
);

DROP TABLE teaching__translation__revision;
DROP TABLE teaching__translation;
```

The reverse migration's docblock MUST flag the data-loss explicitly (per FR-028).

## 4. `AddRevisionsMigrationGenerator` (NEW)

### 4.1 Detection branch

```php
public function generate(string $entityType): string
{
    $existing = $this->entityTypeManager->getDefinition($entityType);

    if ($existing->isRevisionable()) {
        if ($existing->isTranslatable()) {
            throw StorageMigrationException::noOpPromotion($entityType);
        }
        throw StorageMigrationException::noOpPromotion($entityType);
    }

    if ($existing->isTranslatable()) {
        return $this->generateTwoAxisFromTranslatable($entityType);   // NEW path
    }

    return $this->generateSingleAxisRevisionable($entityType);        // mirror of M-006 pattern
}
```

### 4.2 Two-axis-from-translatable output

For an existing translatable-only entity type `teaching` (with `teaching` and `teaching__translation` tables already populated; no `teaching__revision` table):

```sql
-- Up migration
CREATE TABLE teaching__revision (
  vid INTEGER PRIMARY KEY,
  tid INTEGER NOT NULL,
  revision_created_at TEXT NOT NULL,
  revision_author INTEGER,
  revision_log TEXT,
  -- non-translatable field columns
  community_id INTEGER,
  starts_at TEXT
);
CREATE INDEX idx_teaching_rev_tid ON teaching__revision (tid, vid DESC);

CREATE TABLE teaching__translation__revision (
  vid INTEGER PRIMARY KEY,
  tid INTEGER NOT NULL,
  langcode TEXT NOT NULL,
  revision_created_at TEXT NOT NULL,
  revision_author INTEGER,
  revision_log TEXT,
  title TEXT,
  body TEXT,
  UNIQUE (tid, langcode, vid)
);
CREATE INDEX idx_teaching_tx_rev_lookup ON teaching__translation__revision (tid, langcode, vid DESC);

-- Add vid pointer columns to existing tables:
ALTER TABLE teaching ADD COLUMN vid INTEGER NOT NULL DEFAULT 0;
ALTER TABLE teaching__translation ADD COLUMN vid INTEGER NOT NULL DEFAULT 0;

-- Backfill: create initial entity-revision rows from teaching's current non-translatable values.
INSERT INTO teaching__revision (vid, tid, revision_created_at, revision_author, revision_log, community_id, starts_at)
SELECT row_number() OVER (ORDER BY tid), tid, datetime('now'), NULL, 'Initial revision (migration backfill)', community_id, starts_at
FROM teaching;

UPDATE teaching SET vid = (SELECT vid FROM teaching__revision WHERE teaching__revision.tid = teaching.tid);

-- Backfill: create initial translation-revision rows from teaching__translation's current values.
INSERT INTO teaching__translation__revision (vid, tid, langcode, revision_created_at, revision_author, revision_log, title, body)
SELECT (SELECT max(vid) FROM teaching__revision) + row_number() OVER (ORDER BY tid, langcode), tid, langcode, datetime('now'), NULL, 'Initial revision (migration backfill)', title, body
FROM teaching__translation;

UPDATE teaching__translation SET vid = (
  SELECT vid FROM teaching__translation__revision
  WHERE teaching__translation__revision.tid = teaching__translation.tid
    AND teaching__translation__revision.langcode = teaching__translation.langcode
);

-- Remove the non-translatable columns from teaching (they live on teaching__revision now):
ALTER TABLE teaching DROP COLUMN community_id;
ALTER TABLE teaching DROP COLUMN starts_at;
ALTER TABLE teaching__translation DROP COLUMN title;
ALTER TABLE teaching__translation DROP COLUMN body;
```

### 4.3 Reverse migration

Symmetric to §3.3 — collapses two-axis back to translatable-only. Data loss: all non-current revisions dropped. Docblock flags the loss.

## 5. CLI surface

```
bin/waaseyaa make:storage-migration <entity_type> --add-translations    # M-006 flag; extended to handle revisionable target
bin/waaseyaa make:storage-migration <entity_type> --add-revisions       # NEW flag (FR-025)
```

Both flags are mutually exclusive — passing both raises a validation error before generator dispatch.

## 6. Boot-time field guard (FR-006)

Separate from the migration generator, the storage schema sync (typically run on kernel boot or via `bin/waaseyaa optimize:manifest`) validates field definitions:

```php
foreach ($entityType->fields() as $field) {
    if ($field->isTranslatable() && in_array($field->backend(), ['vector', 'remote'], true)) {
        throw StorageMigrationException::unsupportedTwoAxisField($field->name(), $field->backend());
    }
}
```

## 7. Test contract (WP06)

`AddTranslationsMigrationGeneratorTwoAxisTest` (unit + integration):

1. Generator invoked on a revisionable-only entity type emits two-axis migration SQL (creates `__translation` + `__translation__revision` tables; backfills from `__revision`).
2. Generator invoked on an already-two-axis entity type raises `StorageMigrationException::noOpPromotion`.
3. Generator invoked on a non-translatable + non-revisionable type emits M-006 single-axis path (regression gate; M-006 behavior unchanged).
4. Generated migration is reversible; reverse migration's docblock flags data loss.

`AddRevisionsMigrationGeneratorTest` (unit + integration):

1. Generator invoked on a translatable-only entity type emits two-axis migration SQL.
2. Generator invoked on a revisionable-only entity type emits single-axis revisionable path.
3. Generator invoked on an already-two-axis entity type raises `StorageMigrationException::noOpPromotion`.
4. Generated migration is reversible; data-loss flagged.

`TwoAxisMigrationGeneratorIntegrationTest` (integration, end-to-end):

1. Promote `teaching` from revisionable-only → two-axis. Existing revisions preserved as default-langcode revisions. Pre-migration `teaching__revision` rows produce identical `teaching__translation__revision` rows for the default langcode.
2. Promote `teaching` from translatable-only → two-axis. Existing translation rows become initial per-langcode revisions.

## 8. Stable surface

`--add-revisions` CLI flag on `bin/waaseyaa make:storage-migration` lands on charter §5.3 stable-surface map at mission close (WP08). `--add-translations` is already on the map from M-006; its semantic extension to two-axis is documented in the M-006 entry's revision history.

# Phase 1 Data Model — M-004 Entity Storage Translatable + Revisionable

**Date:** 2026-05-16
**Spec:** [`spec.md`](spec.md) (revalidated 2026-05-17, commit `de5c6eba6`)
**Plan:** [`plan.md`](plan.md)
**Research:** [`research.md`](research.md)

Captures the storage shapes, value-object signatures, and exception factory definitions the implementation must produce. Normative for WP01..WP04.

---

## 1. Composite-PK schema shapes

### 1.1 sql-column backend (illustrative for entity type `teaching`)

Single-axis revisionable-only types (M-006 substrate) and single-axis translatable-only types (M-006 substrate) are unchanged. Two-axis types use **four** tables.

```sql
-- Primary table — one row per entity. Tracks default-langcode + entity-level primary current revision.
CREATE TABLE teaching (
  tid              INTEGER PRIMARY KEY,
  uuid             TEXT NOT NULL UNIQUE,
  default_langcode TEXT NOT NULL,
  vid              INTEGER NOT NULL,            -- FK to teaching__revision.vid (default-langcode current revision)
  -- (no field columns — non-translatable values live on the entity-revision row)
);

-- Per-translation pointer table — one row per (entity, langcode). Tracks per-langcode current revision.
CREATE TABLE teaching__translation (
  tid       INTEGER NOT NULL,
  langcode  TEXT NOT NULL,
  vid       INTEGER NOT NULL,                   -- FK to teaching__translation__revision.vid
  PRIMARY KEY (tid, langcode)
);

-- Default-langcode revision table — one row per default-langcode revision. Stores non-translatable field values.
CREATE TABLE teaching__revision (
  vid                  INTEGER PRIMARY KEY,
  tid                  INTEGER NOT NULL,
  revision_created_at  TEXT NOT NULL,
  revision_author      INTEGER,
  revision_log         TEXT,
  -- non-translatable field columns (e.g. community_id, starts_at)
  community_id         INTEGER,
  starts_at            TEXT
);
CREATE INDEX idx_teaching_rev_tid ON teaching__revision (tid, vid DESC);

-- Translation-revision table — one row per (entity, langcode, vid). Stores translatable field values.
CREATE TABLE teaching__translation__revision (
  vid                  INTEGER PRIMARY KEY,
  tid                  INTEGER NOT NULL,
  langcode             TEXT NOT NULL,
  revision_created_at  TEXT NOT NULL,
  revision_author      INTEGER,
  revision_log         TEXT,
  -- translatable field columns (e.g. title, body)
  title                TEXT,
  body                 TEXT,
  UNIQUE (tid, langcode, vid)
);
CREATE INDEX idx_teaching_tx_rev_lookup ON teaching__translation__revision (tid, langcode, vid DESC);
```

**Invariants:**
- `teaching.vid` = `teaching__translation.vid` for `(tid, default_langcode)`. Enforced by `RevisionableStorageDriver` in a single transaction (FR-008).
- Every `(tid, langcode)` in `teaching__translation` has at least one row in `teaching__translation__revision` for the same `(tid, langcode)` (FR-019).
- `teaching__revision.vid` is referenced by exactly one default-langcode row in `teaching__translation` (per `tid`), and zero-or-more non-default-langcode rows that fall back to it for non-translatable values (FR-005).

### 1.2 sql-blob backend (illustrative for entity type `teaching`)

```sql
-- Primary table — one row per entity. Carries the default-langcode revision blob inline for fast primary loads.
CREATE TABLE teaching (
  tid              INTEGER PRIMARY KEY,
  uuid             TEXT NOT NULL UNIQUE,
  default_langcode TEXT NOT NULL,
  vid              INTEGER NOT NULL,
  _data            TEXT NOT NULL   -- JSON blob of non-translatable fields for current default-langcode revision
);

-- Translation-revision table — one row per (entity, langcode, vid). _data carries translatable fields.
CREATE TABLE teaching__translation__revision (
  vid                  INTEGER PRIMARY KEY,
  tid                  INTEGER NOT NULL,
  langcode             TEXT NOT NULL,
  revision_created_at  TEXT NOT NULL,
  revision_author      INTEGER,
  revision_log         TEXT,
  _data                TEXT NOT NULL,   -- JSON blob of translatable fields for this langcode at this revision
  UNIQUE (tid, langcode, vid)
);
CREATE INDEX idx_teaching_tx_rev_lookup ON teaching__translation__revision (tid, langcode, vid DESC);

-- Per-translation pointer table (same as sql-column shape)
CREATE TABLE teaching__translation (
  tid       INTEGER NOT NULL,
  langcode  TEXT NOT NULL,
  vid       INTEGER NOT NULL,
  PRIMARY KEY (tid, langcode)
);
```

**Notes:**
- sql-blob backend stores non-translatable field values on the primary `teaching` row's `_data` blob (no separate `teaching__revision` table — the historical default-langcode revision values are reconstructed from `teaching__translation__revision` rows for the default langcode, which carry full per-revision snapshots).
- For revisionable-only (no translation) sql-blob types, M-006's existing shape is unchanged.

### 1.3 Field-level allocation rule (FR-006, §5.3)

| Field flag | sql-column allocation | sql-blob allocation |
|---|---|---|
| `FieldDefinition::translatable()` ON | `teaching__translation__revision` columns | `teaching__translation__revision._data` JSON |
| `FieldDefinition::translatable()` OFF | `teaching__revision` columns | `teaching._data` JSON |
| backend = `vector` OR `remote` AND `translatable()` ON | **forbidden** — raises `StorageMigrationException::unsupportedTwoAxisField($fieldName, $backend)` at boot |

---

## 2. Per-revision row layout

### 2.1 sql-column

A `teaching__translation__revision` row for `(tid=42, langcode='oj', vid=7)`:

```
vid: 7
tid: 42
langcode: oj
revision_created_at: 2026-05-16T14:32:11Z
revision_author: 5
revision_log: "Updated Elder's phrasing"
title: "<Anishinaabemowin title>"
body: "<Anishinaabemowin body>"
```

A `teaching__revision` row for `(vid=5)` (default-langcode revision referenced by oj-revision 7):

```
vid: 5
tid: 42
revision_created_at: 2026-05-15T09:11:22Z
revision_author: 3
revision_log: "Added community context"
community_id: 12
starts_at: 2026-06-01T00:00:00Z
```

When `$entity->getTranslation('oj')` is loaded, the runtime merges translatable fields from `teaching__translation__revision` (vid=7) with non-translatable fields from `teaching__revision` (vid=5) — fallback per FR-005.

### 2.2 sql-blob

A `teaching__translation__revision` row for `(tid=42, langcode='oj', vid=7)`:

```
vid: 7
tid: 42
langcode: oj
revision_created_at: 2026-05-16T14:32:11Z
revision_author: 5
revision_log: "Updated Elder's phrasing"
_data: '{"title":"<...>","body":"<...>"}'
```

The primary `teaching` row carries the default-langcode revision's blob inline:

```
tid: 42
uuid: 01HXR...
default_langcode: en
vid: 5
_data: '{"community_id":12,"starts_at":"2026-06-01T00:00:00Z"}'
```

---

## 3. SaveContext shape extension

`Waaseyaa\EntityStorage\SaveContext` (existing in M-006) gains one new builder and one new readonly property.

### 3.1 Properties (post-extension)

```php
final class SaveContext
{
    private function __construct(
        public readonly bool $withoutNewRevision = false,
        public readonly ?string $langcode = null,
        public readonly bool $isImport = false,
        public readonly ?array $translations = null,   // NEW (FR-013)
    ) {}
}
```

`$translations` is `?list<string>` (non-empty list of langcodes when set, null otherwise).

### 3.2 Builder (new)

```php
public function withTranslations(array $langcodes): self
```

- Validates `$langcodes` is non-empty; raises `\InvalidArgumentException` if empty.
- Validates each langcode is a non-empty string.
- Returns new immutable instance carrying `$translations: $langcodes`.

### 3.3 Precedence rule

When BOTH `$langcode` and `$translations` are non-null on a `SaveContext`, the coordinator uses `$translations` (multi-language atomic save) and ignores `$langcode`. Validators MUST NOT treat the combination as an error — callers may legitimately layer `withLangcode()` then `withTranslations()` in fluent style.

### 3.4 Event semantics (FR-014)

`AfterSaveEvent::affectedLangcodes()` (M-006 shape unchanged):

| Save kind | `affectedLangcodes` value |
|---|---|
| Default (`SaveContext::default()`) | `null` (M-006 behavior; cache invalidator falls back to `[$entity->activeLangcode()]`) |
| `withLangcode('fr')` | `['fr']` (single-element list) |
| `withTranslations(['en', 'oj', 'fr'])` | `['en', 'oj', 'fr']` |
| Non-translatable field change (default-langcode revision write) | `[$entity->defaultLangcode()]` |

`ListingCacheInvalidator` consumes `affectedLangcodes` unchanged (M-007).

---

## 4. Revisionable interface signature extension

`Waaseyaa\Entity\RevisionableEntityInterface` (existing in M-006) gains an optional langcode parameter on `listRevisions`:

```php
public function listRevisions(?string $langcode = null): iterable;
```

- `null` (default): returns revisions of ALL langcodes interleaved by creation order (FR-018, ordered by `revision_created_at DESC`, then by surrogate `vid` for stable tie-breaking).
- Non-null: returns revisions scoped to one langcode.

Single-axis revisionable-only entities (M-006) ignore the parameter (no langcode dimension); the default behavior is unchanged. Backwards compatible.

---

## 5. Exception factory definitions

### 5.1 `Waaseyaa\Entity\Exception\EntityTranslationException` (existing class — add factory)

Existing factories (M-006, unchanged):
- `translationNotFound(string $langcode): self`
- `cannotRemoveDefault(string $langcode): self`
- `langcodeRequired(): self`
- `notTranslatable(string $entityTypeId): self`
- `translationAlreadyExists(string $langcode): self`

**New factory (FR-017, FR-040):**

```php
public static function historicalRevisionWrite(int $vid, string $langcode): self
{
    return new self(
        sprintf('Cannot save a historical revision (vid=%d, langcode=%s); load the current revision and save that.', $vid, $langcode),
        code: 'historical_revision_write',
    );
}
```

### 5.2 `Waaseyaa\EntityStorage\Exception\StorageMigrationException` (NEW class)

Single new class. Two factories.

```php
final class StorageMigrationException extends \RuntimeException
{
    public readonly string $errorCode;

    private function __construct(string $message, string $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
    }

    public static function noOpPromotion(string $entityType): self
    {
        return new self(
            sprintf('Entity type "%s" is already two-axis (revisionable + translatable); no migration needed.', $entityType),
            errorCode: 'no_op_promotion',
        );
    }

    public static function unsupportedTwoAxisField(string $fieldName, string $backend): self
    {
        return new self(
            sprintf('Field "%s" uses backend "%s" which does not support translation × revision composition; allowed backends are sql-column and sql-blob.', $fieldName, $backend),
            errorCode: 'unsupported_two_axis_field',
        );
    }
}
```

**Stability (FR-041, FR-042):** `errorCode` strings are stable surface. Class name is stable surface. Future factories may be added; renames follow charter §4 deprecation cycle.

---

## 6. Storage driver signature additions

`Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver` (existing M-006) — read/write signatures gain an optional `?string $langcode` parameter:

```php
public function writeRevision(int|string $entityId, array $values, ?string $log = null, ?string $langcode = null): int;
public function readRevision(int|string $entityId, int $vid, ?string $langcode = null): ?array;
public function readMultipleRevisions(int|string $entityId, array $vids, ?string $langcode = null): array;
public function getLatestRevisionId(int|string $entityId, ?string $langcode = null): ?int;
public function getRevisionIds(int|string $entityId, ?string $langcode = null): array;
public function deleteRevision(int|string $entityId, int $vid, ?string $langcode = null): void;
public function deleteAllRevisions(int|string $entityId, ?string $langcode = null): void;
```

- `null` langcode: routes to the entity-revision table (default-langcode behavior; M-006 unchanged for single-axis).
- Non-null langcode: routes to the translation-revision table for the specified langcode.
- For single-axis revisionable-only entity types, non-null langcode raises `EntityTranslationException::notTranslatable($entityTypeId)`.

---

## 7. Listing pipeline value shapes (no changes)

M-007's `Filter`, `FilterDefinition`, `SortDefinition`, `Operator`, `Pagination`, `ListingResult`, `ListingDefinition`, `ListingCacheInvalidator` are consumed unchanged. M-004 adds **no** new value objects on the listing surface.

New internal component (FR-033a): `Waaseyaa\EntityStorage\Listing\TwoAxisFilterResolver` (or equivalent hook in the existing resolver — decided at implementation). Service-class; not a value object. Routes `Filter::langcode($code)` against two-axis entity types through the per-`(entity, langcode)` current-revision pointer.

---

## 8. Access policy signature shape

Existing `AccessPolicyInterface::access()` signature gains an optional trailing parameter (illustrative — final shape decided in WP05):

```php
public function access(
    EntityInterface $entity,
    AccountInterface $account,
    string $operation,
    ?RevisionableEntityInterface $revision = null,   // NEW (FR-020, R-07)
): AccessResult;
```

- For `view_revision`: `$entity` is the translation instance; `$revision` is the historical revision.
- For all other operations: `$revision` is null (backward compatible).
- Fallback rules: `view_revision` → `view`; `translate` → `edit` (FR-021, FR-022).

See [`contracts/access-policy-revision.md`](contracts/access-policy-revision.md) for normative signature + worked Minoo example.

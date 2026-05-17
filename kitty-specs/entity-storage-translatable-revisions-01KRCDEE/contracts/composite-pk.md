# Contract — Composite PK `(tid, langcode, vid)` semantics

**Status:** Normative for WP01 + WP02.
**Owners:** WP01 (sql-column) and WP02 (sql-blob).
**Refs:** FR-001..FR-008; spec §5.

---

## 1. Scope

Defines the storage shape and invariants for revision tables on entity types that declare BOTH `revisionable: true` AND `translatable: true`. Single-axis revisionable-only types and single-axis translatable-only types are unchanged from M-006.

## 2. Identity model

For a two-axis entity (using `teaching` as illustrative type):

| Concept | Storage location | Cardinality |
|---|---|---|
| Entity instance | `teaching.tid` | one row per entity |
| Default-langcode | `teaching.default_langcode` | one value per entity |
| Entity-level primary current revision | `teaching.vid` | one value per entity; equals `teaching__translation.vid` for `(tid, default_langcode)` |
| Per-translation pointer | `teaching__translation.(tid, langcode, vid)` | one row per `(entity, langcode)` |
| Default-langcode revision | `teaching__revision.vid` | one row per default-langcode revision |
| Per-translation revision | `teaching__translation__revision.(tid, langcode, vid)` | one row per `(entity, langcode, vid)` |

## 3. Primary key shape

| Table | Primary key | Composite uniqueness | Notes |
|---|---|---|---|
| `teaching` | `tid` | — | unchanged from single-axis |
| `teaching__translation` | `(tid, langcode)` | — | new in two-axis |
| `teaching__revision` | `vid` (surrogate) | — | unchanged from single-axis revisionable-only |
| `teaching__translation__revision` | `vid` (surrogate) | **UNIQUE (tid, langcode, vid)** | new in two-axis |

**Rationale (R-01).** Surrogate `vid INTEGER PRIMARY KEY` on the translation-revision table keeps `loadRevision($vid)` ergonomic and gives O(log n) ordering for cross-langcode interleaved `listRevisions()`. Composite UNIQUE expresses the logical primary key without sacrificing query patterns.

## 4. Default-langcode anchor invariant (FR-008)

```
INVARIANT: teaching.vid = teaching__translation.vid  WHERE teaching.default_langcode = teaching__translation.langcode AND teaching.tid = teaching__translation.tid
```

- Enforced by `RevisionableStorageDriver` writing both rows in the same transaction (default-langcode revision write path).
- Violation indicates storage corruption; integration tests assert the invariant after every save.
- The duplication is **intentional**: `teaching.vid` enables single-query primary loads without joining the translation table (§9 Q4 resolved keep-both).

## 5. Sequencing rules

### 5.1 vid allocation

- Surrogate `vid` is monotonic AUTOINCREMENT across BOTH `teaching__revision` and `teaching__translation__revision`.
- Implementation MAY share a single sequence for both tables (recommended) OR use separate sequences (acceptable if `listRevisions()` ordering remains deterministic).
- If separate sequences: stable tie-breaking via `(revision_created_at, langcode)` lexicographic order is REQUIRED for interleaved `listRevisions()` (FR-018).

### 5.2 Per-langcode revision sequence

- For a fixed `(tid, langcode)`, vids in `teaching__translation__revision` MUST be strictly monotonic by `revision_created_at` (assertion on save).

## 6. Read path contracts

### 6.1 Default-langcode primary load

```
$entity = $storage->load('teaching', 42);
→ Reads `teaching` row (vid pointer).
→ Reads `teaching__revision` at that vid (non-translatable fields).
→ For sql-column: reads `teaching__translation__revision` for (42, default_langcode, teaching.vid).
→ For sql-blob: default-langcode translatable fields available from `teaching__translation__revision` (default-langcode current row).
→ Returns entity with activeLangcode = default_langcode.
```

### 6.2 Non-default-langcode load (FR-005 fallback)

```
$entity->getTranslation('oj')
→ Reads `teaching__translation` row for (42, 'oj') → gets oj_vid.
→ Reads `teaching__translation__revision` at oj_vid (translatable fields for oj).
→ Single-step fallback for non-translatable fields: reads `teaching__revision` at teaching.vid.
→ Returns translation instance with activeLangcode = 'oj'.
```

Exactly **one** extra row lookup vs the single-axis case (NFR-A).

### 6.3 Historical revision load (FR-017)

```
$entity->getTranslation('oj')->loadRevision(7)
→ Reads `teaching__translation__revision` at vid=7, asserts langcode='oj'.
→ Returns historical instance; isCurrentRevision() = false.
→ Calling save() raises EntityTranslationException::historicalRevisionWrite(7, 'oj').
```

## 7. Write path contracts

### 7.1 Save a single non-default-langcode translation (FR-009, FR-010)

```
1. Allocate new vid (monotonic).
2. INSERT teaching__translation__revision (vid, tid, langcode='oj', translatable fields...).
3. UPDATE teaching__translation SET vid=<new> WHERE tid=42 AND langcode='oj'.
4. (no change to teaching.vid; no change to teaching__revision; other-langcode pointers untouched).
```

### 7.2 Save with non-translatable field change (FR-011)

```
1. Allocate new vid_entity (monotonic).
2. INSERT teaching__revision (vid_entity, tid, non-translatable fields...).
3. UPDATE teaching SET vid=vid_entity WHERE tid=42.
4. Allocate new vid_translation (monotonic).
5. INSERT teaching__translation__revision (vid_translation, tid, langcode=default, translatable fields...).
6. UPDATE teaching__translation SET vid=vid_translation WHERE tid=42 AND langcode=default.
7. (Other-langcode current revisions continue to reference teaching.vid for non-translatable values via fallback — no per-langcode revision is needed unless that langcode's translatable fields also changed.)
```

### 7.3 Multi-language atomic save (FR-013)

Per §6.2 of spec; transaction wraps the entire iteration. Partial failure raises `PartialSaveException` (per ADR 010 §6.5) and rolls back.

## 8. Backend-specific notes

### 8.1 sql-column

- `teaching__revision` and `teaching__translation__revision` are sibling tables; each carries its own column set per `FieldDefinition::translatable()` partition (M-006 `TranslationSchemaHandler::partitionTranslatableFields()` reused).

### 8.2 sql-blob

- `teaching` row's `_data` blob carries the default-langcode revision's non-translatable values inline for fast primary loads.
- `teaching__translation__revision._data` carries the translatable values for the `(tid, langcode, vid)` triple.
- No separate `teaching__revision` table — non-translatable historical values are reconstructed from the primary table at the snapshot taken when `teaching.vid` was last updated (the default-langcode revision).

### 8.3 Forbidden combinations (FR-006)

A field declared `translatable()` ON a `vector` or `remote` backend MUST raise `StorageMigrationException::unsupportedTwoAxisField($fieldName, $backend)` at schema sync / kernel boot. Caught by the `TranslationSchemaHandler` extended for two-axis emission.

## 9. Single-axis backward compatibility

- Single-axis revisionable-only types use the M-006 surrogate-PK shape unchanged: `teaching__revision (vid PRIMARY KEY, tid, ...)`. No langcode column. WP01's `RevisionTableBuilder` extension emits this path by default.
- Single-axis translatable-only types use the M-006 `teaching__translation` shape unchanged: `(tid, langcode)` PK, no `vid` column. No revision-tracking.
- Promotion paths from single-axis to two-axis are handled by the migration generators (WP06, see `two-axis-migration.md`).

## 10. Conformance tests (WP01 / WP02)

- `SqlColumnTwoAxisStorageTest` and `SqlBlobTwoAxisStorageTest` subclass `TwoAxisStorageContract` and assert:
  1. Default-langcode primary load returns entity with activeLangcode = default.
  2. Non-default-langcode load applies single-step fallback for non-translatable fields.
  3. Historical revision load returns isCurrentRevision() = false.
  4. Save of one langcode does not mutate other-langcode current-revision pointers.
  5. Non-translatable field change creates ONE new entity-revision row AND one new default-langcode translation-revision row; other-langcode current pointers unchanged.
  6. `teaching.vid` = `teaching__translation.vid` for `(tid, default_langcode)` invariant holds after every save.
  7. M-006 single-axis revisionable-only behavior unchanged (regression gate).

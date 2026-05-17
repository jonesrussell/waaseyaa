# Contract: bin/waaseyaa make:migration --add-translations

**Mission:** M-006 · **Status:** stable surface on merge per stability-charter §5.3
**File:** `packages/cli/src/Command/MakeMigrationCommand.php`
**Normative spec:** [`spec.md` §3.10 + §8](../spec.md#310-migration-generator)
**Work package:** WP11

---

## CLI signature

```
bin/waaseyaa make:migration --add-translations <entity_type_id> --default-langcode <lc>
```

| Argument / flag | Required | Value |
|---|---|---|
| `--add-translations <id>` | yes | An entity-type id registered in the kernel. Must resolve to a content entity type. |
| `--default-langcode <lc>` | yes | Langcode string (e.g. `en`). Generator refuses to run without it (FR-052). |

---

## Pre-conditions

- The entity type identified by `<id>` MUST exist.
- The entity type's `EntityType::translatable` MAY be `false` at generation time; the migration FLIPS it as part of the schema change. (The PHP-side `EntityType` definition is updated by the developer separately.)
- The primary table MUST already have a `langcode` column. Generator emits `MissingLangcodeColumnException` and refuses if absent.
- The schema MUST be in a clean state. Generator refuses if pending migrations exist (consistent with project migration policy).

---

## Generated migration shape

For `sql-column` backend:

```sql
CREATE TABLE <table>__translation (
    entity_id INTEGER NOT NULL,
    langcode VARCHAR(12) NOT NULL,
    <translatable_field_columns...>,
    PRIMARY KEY (entity_id, langcode),
    FOREIGN KEY (entity_id) REFERENCES <table>(entity_id) ON DELETE CASCADE
);

INSERT INTO <table>__translation (entity_id, langcode, <translatable_fields>)
SELECT entity_id, '<default-langcode>', <translatable_fields>
FROM <table>;

ALTER TABLE <table> DROP COLUMN <translatable_field_1>;
ALTER TABLE <table> DROP COLUMN <translatable_field_2>;
-- ... per translatable field

ALTER TABLE <table> ADD COLUMN default_langcode VARCHAR(12) NOT NULL
    DEFAULT '<default-langcode>';
```

For `sql-blob` backend:

```sql
ALTER TABLE <table> ADD COLUMN default_langcode VARCHAR(12) NOT NULL
    DEFAULT '<default-langcode>';

UPDATE <table>
   SET langcode = '<default-langcode>'
 WHERE langcode IS NULL OR langcode = '';

-- Widen primary key
ALTER TABLE <table> DROP PRIMARY KEY;
ALTER TABLE <table> ADD PRIMARY KEY (entity_id, langcode);

-- Move UUID uniqueness to default-langcode row only
CREATE UNIQUE INDEX <table>_uuid_default ON <table>(uuid)
    WHERE langcode = default_langcode;
```

---

## Reverse migration

Generator MUST emit `down()` that:

1. Drops the `<table>__translation` table (sql-column) or restores the narrow primary key (sql-blob).
2. Backfills any lost translatable-field columns onto `<table>` from `<table>__translation` rows where `langcode = default_langcode` (sql-column only).
3. Emits a `// DATA LOSS WARNING:` PHPDoc note: rows for non-default-langcode translations are dropped.

---

## Warnings emitted at generation

| Condition | Warning |
|---|---|
| Existing rows have `langcode` values that differ from `--default-langcode` | "1247 rows have langcode ≠ '<default-langcode>'; preserving as-is. These will become first-class translations." (Non-fatal; preserves data.) |
| Backfill row count would be zero (empty table) | "No existing rows; backfill is a no-op." (Informational.) |
| Reverse migration would drop > 0 rows | "Reverse migration will drop {N} non-default-langcode rows. Data loss." (Stored in `down()` docblock; visible to reviewer.) |

---

## Failure modes

| Failure | Behaviour |
|---|---|
| `--default-langcode` missing | Command refuses with exit code 1; message: "The --default-langcode option is required when using --add-translations." |
| Entity type not found | Exit code 1; "Entity type '<id>' is not registered." |
| Entity type is not a content entity | Exit code 1; "Entity type '<id>' is a config entity; --add-translations is for content entities only." |
| Primary table missing `langcode` column | Exit code 1; `MissingLangcodeColumnException`. |
| Translatable fields not declared in `EntityType` | Exit code 1; "No fields on entity type '<id>' are marked translatable. Mark at least one with FieldDefinition::translatable() before generating this migration." |
| Pending un-applied migrations exist | Exit code 1; "Apply pending migrations first." |

---

## Test coverage (I06 in spec §9.3)

- Generator produces a syntactically valid PHP migration file.
- Forward migration applied to a populated table preserves all data with `langcode = default_langcode = en`.
- Reverse migration applied after forward migration produces an empty diff against pre-forward state when no non-default-langcode rows exist.
- Reverse migration applied with non-default-langcode rows present drops them and emits a logger warning.

---

## Generated file naming

`migrations/YYYY_MM_DD_HHMMSS_add_translations_to_<table>.php`

Same naming convention as other framework migrations.

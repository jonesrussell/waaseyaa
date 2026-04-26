# Field type → column spec (`deriveColumnSpec`)

This document is the review-facing map for **`Waaseyaa\EntityStorage\SqlSchemaHandler::deriveColumnSpec()`** (`packages/entity-storage/src/SqlSchemaHandler.php`). It describes the **Waaseyaa column spec** shape (`type`, optional `length`, `not null`, `default`) produced from `FieldDefinition::getType()` and `getSettings()`.

Downstream, **`Waaseyaa\Database\Schema\DBALSchema::mapFieldType()`** (`packages/database-legacy/src/Schema/DBALSchema.php`) translates those Waaseyaa `type` strings into Doctrine DBAL abstract types for DDL. Vendor-specific SQL (e.g. MySQL `LONGTEXT`, `INT UNSIGNED`) is **not** spelled in `deriveColumnSpec()`; it emerges from the active DBAL platform when SQL is generated.

## Mapping table (canonical)

| `FieldDefinition::getType()` (case-insensitive) | Waaseyaa spec from `deriveColumnSpec()` | Notes |
|-----------------------------------------------|------------------------------------------|--------|
| `string` | `varchar` + `length` default 255 (from settings or default) | |
| `text` | `text` | |
| `text_long` | `text` | Same abstract type as `text`; platform may render long text differently. |
| `uri` | `varchar` + `length` default **2048** (overridable via settings `length`) | Bounded URI storage. |
| `entity_reference` | `int` | Assumes integer PK on the target entity; string/config IDs need a follow-up arm if introduced. |
| `integer` / `int` | `int` | |
| `boolean` / `bool` | `boolean` | |
| `float` / `decimal` / `numeric` / `number` | `float` | |
| *(unknown)* | `text` + **`LoggerInterface::warning()`** | Log context includes `entity_type`, `field`, `field_type`. |

## `FieldStorage` interaction

`FieldStorage::Data` vs `FieldStorage::Column` is decided **before** `deriveColumnSpec()` runs: fields marked **`Data`** are not materialized as bundle columns, so **no column spec** is built for them on that path. See `docs/specs/bundle-scoped-storage.md` and `FieldStorage` in `packages/field/src/FieldStorage.php`.

## Foreign keys

Bundle subtables already declare an FK from the subtable’s id column to the base table (`buildBundleSubtableSpec()` in `SqlSchemaHandler`). **`entity_reference`** column specs here are **not** automatically paired with cross-table FK constraints to arbitrary target entity tables; that remains application/migration responsibility unless extended later.

## Related specs

- `docs/specs/bundle-scoped-storage.md` — bundle subtables and `deriveColumnSpec()` introduction.
- `docs/specs/extraction-log.md` — shared-mapper promotion notes.

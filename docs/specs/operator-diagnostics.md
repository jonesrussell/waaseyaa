# Operator Diagnostics

## Purpose

Provides operator-facing health checks, structured diagnostic codes, and CLI reporting for the Waaseyaa framework. Operators can detect configuration issues, schema drift, database problems, and ingestion pipeline health without reading application logs.

## Architecture Overview

```
DiagnosticCode (enum) → DiagnosticEntry (value object) → DiagnosticEmitter (error_log)
                                                        ↘
BootDiagnosticReport ─→ HealthChecker ─→ HealthCheckResult[] ─→ CLI Commands
                         (service)        (value objects)        (health:check, health:report, schema:check)
```

### Key Classes

| Class | Package | Purpose |
|-------|---------|---------|
| `DiagnosticCode` | foundation | Enum of all operator-facing diagnostic codes |
| `DiagnosticEntry` | foundation | Structured log entry with code, message, context, remediation |
| `DiagnosticEmitter` | foundation | Writes DiagnosticEntry to `error_log()` as JSON |
| `BootDiagnosticReport` | foundation | Snapshot of entity type registry status at boot |
| `HealthChecker` | foundation | Runs all health checks, returns structured results |
| `HealthCheckerInterface` | foundation | Interface for testability (HealthChecker is final) |
| `HealthCheckResult` | foundation | Value object with pass/warn/fail factories |
| `HealthCheckCommand` | cli | `health:check` — fast health probe with table/JSON output; registered via `HasNativeCommandsInterface` |
| `HealthReportCommand` | cli | `health:report` — full diagnostic report with system info; registered via `HasNativeCommandsInterface` |
| `SchemaCheckCommand` | cli | `schema:check` — standalone schema drift detection; registered via `HasNativeCommandsInterface` |

## Diagnostic Codes

### Boot-time Codes

| Code | Severity | Trigger |
|------|----------|---------|
| `DEFAULT_TYPE_MISSING` | error | No entity types registered at boot |
| `DEFAULT_TYPE_DISABLED` | error | All registered entity types are disabled |
| `UNAUTHORIZED_V1_TAG` | warning | v1.0 git tag without owner approval sentinel |
| `TAG_QUARANTINE_DETECTED` | warning | Existing unauthorized v1.0 tags in repo |
| `MANIFEST_VERSIONING_MISSING` | warning | Defaults manifest lacks project_versioning block |
| `NAMESPACE_RESERVED` | warning | Extension tried to register a `core.` prefixed type |

### Runtime Health Codes

| Code | Severity | Trigger |
|------|----------|---------|
| `DATABASE_UNREACHABLE` | error | SQLite file missing, corrupt, or inaccessible |
| `DATABASE_SCHEMA_DRIFT` | error | Table columns don't match expected entity type definition |
| `MISSING_BUNDLE_SUBTABLE` | error | Bundle has registered fields but `{base_table}__{bundle}` subtable does not exist |
| `ORPHAN_BUNDLE_SUBTABLE` | warning | `{base_table}__{bundle}` subtable exists but no registered bundle carries fields for it |
| `FK_ENFORCEMENT_DISABLED` | error | SQLite `PRAGMA foreign_keys` is OFF — subtable CASCADE deletes will not propagate |
| `CACHE_DIRECTORY_UNWRITABLE` | warning | `storage/framework/` exists but not writable |
| `STORAGE_DIRECTORY_MISSING` | warning | `storage/framework/` does not exist |
| `INGESTION_LOG_OVERSIZED` | warning | Ingestion log exceeds 10,000 entries |
| `INGESTION_RECENT_FAILURES` | warning | >25% of ingestion attempts rejected |
| `COLUMN_DATA_STORAGE_DRIFT` | warning | A field registered with `FieldStorage::Data` still has a backing column on the base table or a registered bundle subtable. New writes go to `_data`; the column holds stale values. Author a migration to drop the column or revert the storage hint. |

### Code Structure

Each `DiagnosticCode` case provides:
- `defaultMessage()` — human-readable description of the problem
- `remediation()` — actionable steps to resolve the issue
- `severity()` — `error` or `warning` for health check display

## Health Check Pipeline

`HealthChecker::runAll()` executes checks in three groups:

### 1. Boot Checks

- **Entity types** — at least one entity type must be registered and enabled
- Source: `BootDiagnosticReport` snapshot from kernel boot

### 2. Runtime Checks

- **Database** — `SELECT 1` connectivity test via `DBALDatabase::query()`
- **Schema drift** — compares `PRAGMA table_info()` against `SqlSchemaHandler.buildTableSpec()` expected columns for each registered entity type. Multi-bundle types additionally enumerate their `{base_table}__{bundle}` subtables (see below).
- **Column-vs-data storage drift** — for each entity type with a registered `FieldStorage::Data` field, probes `PRAGMA table_info()` for a column whose name matches the field on the base table (core fields) or the matching `{base_table}__{bundle}` subtable (bundle fields). Emits `COLUMN_DATA_STORAGE_DRIFT` per occurrence. Skipped silently when no `FieldDefinitionRegistry` is injected. SQLite-only at present — orphan detection went portable in #1301; column-data drift's PRAGMA path is tracked separately for dialect parity.
- **Foreign key enforcement** — SQLite only: checks `PRAGMA foreign_keys`; emits `FK_ENFORCEMENT_DISABLED` when off. Skipped for dialects with default-on enforcement.
- **Storage directory** — `storage/framework/` existence check
- **Cache directory** — `storage/framework/` writability check

### 3. Ingestion Checks

- **Log size** — warns if ingestion.jsonl exceeds 10,000 entries
- **Error rate** — warns if >25% of entries are `rejected`
- Source: `IngestionLogger::read()` from `storage/framework/ingestion.jsonl`

## Schema Drift Detection

Compares actual SQLite table schema against expected definition for each entity type.

For multi-bundle entity types with registered bundle-scoped fields, drift detection additionally enumerates `{base_table}__{bundle}` subtables. See [`bundle-scoped-storage.md`](./bundle-scoped-storage.md#drift-diagnostic) for the per-subtable drift contract, missing-subtable and `ORPHAN_BUNDLE_SUBTABLE` codes.

### Algorithm

1. Iterate all registered entity types via `EntityTypeManagerInterface::getDefinitions()`
2. Skip types whose table doesn't exist yet (lazy creation, not drift)
3. For each existing table, run `PRAGMA table_info("{table}")` to get actual columns
4. Build expected columns from `EntityTypeInterface::getKeys()`:
   - Content entities (has `uuid` key): ID column = `INTEGER` (serial), plus `uuid` = `TEXT`
   - Config entities (no `uuid` key): ID column = `TEXT` (varchar)
   - Common columns: bundle, label, langcode, `_data` — all `TEXT`
5. Compare actual vs expected: check for missing columns, type mismatches
6. SQLite type normalization: `varchar` → `TEXT`, `serial` → `INTEGER` (affinity rules)

### Drift Entry Shape

```php
['column' => 'type', 'issue' => 'type mismatch: expected TEXT, got INTEGER']
['column' => 'uuid', 'issue' => 'missing']
```

### Bundle Subtable Drift

When `HealthChecker` is constructed with a `FieldDefinitionRegistryInterface`, multi-bundle entity types (those with a `bundleEntityType`) are additionally enumerated per bundle:

1. For each bundle returned by `bundleNamesFor($entityTypeId)` with non-empty `bundleFieldsFor()`, the expected subtable is `{base_table}__{bundle}`.
2. If the subtable is missing, emit `MISSING_BUNDLE_SUBTABLE` under the name `Schema: {base_table}__{bundle}`. `context.table` carries the subtable name.
3. If the subtable exists, compare its columns against the bundle's registered field names. Missing columns are reported as `DATABASE_SCHEMA_DRIFT` under the subtable name, again with `context.table` set.
4. Orphan detection enumerates every table via `SchemaInterface::listTableNames()` (Doctrine's `AbstractSchemaManager::listTableNames()` under the hood — portable across SQLite, MySQL, PostgreSQL, and any other DBAL-supported driver) and filters in PHP for entries starting with `{base_table}__`. Any subtable not accounted for by a registered non-empty bundle is reported as `ORPHAN_BUNDLE_SUBTABLE` (warn, informational — auto-drop is never performed; author a cleanup migration). Issue #1301 (deferred mission #1257 WP09) replaced the SQLite-only `sqlite_master` LIKE query with this portable path.

Single-bundle entity types (no `bundleEntityType`) are unchanged — subtable enumeration is skipped entirely.

## CLI Commands

The three diagnostic commands are implemented in `packages/cli/src/Command/` and registered by `CliServiceProvider` via `HasNativeCommandsInterface::nativeCommands()`. The `CliKernel` dispatches them; there is no Symfony Console dependency in the registration or dispatch path. See [`docs/specs/cli-kernel.md`](./cli-kernel.md) for the provider contract and kernel lifecycle.

### `health:check`

Fast, script-friendly health probe.

```
$ waaseyaa health:check
+--------+-------------------+------------------------------------------------------+
| Status | Check             | Message                                              |
+--------+-------------------+------------------------------------------------------+
| PASS   | Entity types      | 12 entity type(s) registered and enabled.            |
| PASS   | Database          | Database is accessible.                              |
| PASS   | Schema drift      | All entity table schemas match expected definitions. |
| PASS   | Storage directory | storage/framework/ exists.                           |
| PASS   | Cache directory   | storage/framework/ is writable.                      |
| PASS   | Ingestion log     | No ingestion entries recorded.                       |
+--------+-------------------+------------------------------------------------------+

All health checks passed.
```

**Options:**
- `--json` — output results as JSON array

**Exit codes:**
- `0` — all checks pass
- `1` — warnings present
- `2` — failures present

Non-passing checks show remediations below the table.

### `schema:check`

Standalone schema drift detection with column-level detail.

```
$ waaseyaa schema:check
OK All entity table schemas match expected definitions.
```

When drift is detected:
```
DRIFT Schema: node_type: Table "node_type" has 1 column(s) with schema drift.
+--------+--------------------------------------------+
| Column | Issue                                      |
+--------+--------------------------------------------+
| type   | type mismatch: expected TEXT, got INTEGER   |
+--------+--------------------------------------------+
  Remediation: Delete the SQLite database and restart to recreate tables.
```

**Options:**
- `--json` — output results as JSON

**Exit codes:**
- `0` — no drift
- `1` — drift detected

### `health:report`

Full diagnostic report for operator review or support tickets.

Sections:
1. **System Information** — PHP version, OS, SAPI, database path, config dir
2. **Health Checks** — same table as `health:check`
3. **Ingestion Summary** — total/accepted/rejected, error rate, top error codes
4. **Remediations** — for any non-passing checks

**Options:**
- `--json` — output as structured JSON
- `--output <file>` — write JSON report to file (requires `--json`)

## HealthCheckResult Value Object

```php
HealthCheckResult::pass('Database', 'DB is accessible.');
HealthCheckResult::warn('Cache', DiagnosticCode::CACHE_DIRECTORY_UNWRITABLE, 'Not writable.');
HealthCheckResult::fail('Database', DiagnosticCode::DATABASE_UNREACHABLE);
```

### Serialized Shape

```json
{
    "name": "Database",
    "status": "fail",
    "code": "DATABASE_UNREACHABLE",
    "message": "The database file is missing, corrupt, or not accessible.",
    "remediation": "Verify the WAASEYAA_DB environment variable...",
    "context": {}
}
```

Pass results omit `code`, `remediation`, and `context` when empty.

## Ingestion Health Summary

Generated by `HealthReportCommand` from `IngestionLogger` data:

| Metric | Description |
|--------|-------------|
| `total_entries` | Total ingestion log entries in retention window |
| `accepted` | Count of accepted envelopes |
| `rejected` | Count of rejected envelopes |
| `error_rate` | Percentage of rejected entries |
| `last_accepted` | Timestamp of most recent accepted entry |
| `last_rejected` | Timestamp of most recent rejected entry |
| `top_error_codes` | Top 5 most frequent error codes |

## File Locations

| File | Purpose |
|------|---------|
| `packages/foundation/src/Diagnostic/` | All diagnostic classes |
| `packages/foundation/tests/Unit/Diagnostic/` | Diagnostic unit tests |
| `packages/cli/src/Command/HealthCheckCommand.php` | health:check CLI |
| `packages/cli/src/Command/HealthReportCommand.php` | health:report CLI |
| `packages/cli/src/Command/SchemaCheckCommand.php` | schema:check CLI |
| `packages/cli/tests/Unit/Command/` | CLI command tests |
| `storage/framework/ingestion.jsonl` | Runtime ingestion log |
| `storage/framework/entity-audit.jsonl` | Entity write audit trail |

## See Also

- [`docs/specs/cli-kernel.md`](./cli-kernel.md) — native CLI kernel architecture, `HasNativeCommandsInterface` provider contract, `CliTester` testing harness, exit-code policy

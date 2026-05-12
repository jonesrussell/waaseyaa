# Contract — Storage Migration Generator CLI

**Owning WP**: WP10.
**Source**: spec §10; ADR 009 (migration manifest substrate).
**Stable surface**: yes (charter §5.2 console surface).

---

## Command

```
bin/waaseyaa make:storage-migration <entity_type_id> [--target=sql-column] [--dry-run] [--force]
```

| Flag | Default | Purpose |
|---|---|---|
| `--target` | `sql-column` | Target backend id. Only `sql-column` is supported in this mission. |
| `--dry-run` | off | Print the migration to stdout; do not write a file. |
| `--force` | off | Overwrite an existing per-type migration file. |

Exit codes: `0` ok, `1` unknown entity type, `2` invalid target, `3` migration file exists (use `--force`), `4` field type without a §8.2 mapping.

## Behavior (normative)

1. Resolve the entity type via `EntityTypeManager`. Fail with code 1 if unknown.
2. Inspect each `FieldDefinition` on the entity type. For each, look up the SQLite/Postgres column type per spec §8.2. Fail with code 4 on unmapped types.
3. Emit a migration file under `packages/<owning-package>/migrations/` (or the project root `migrations/` if no owning package) using the existing migration naming convention. The migration:
   - Adds the new `sql-column` table (and `__revision` table when the entity type is `revisionable: true`).
   - Backfills data from the existing `_data` column (`sql-blob`) into the new columns.
   - Flips `EntityType::$primaryStorageBackend` to `sql-column` (the manifest update is part of the migration's `up()` step or a sibling configuration change documented in the migration's docblock).
   - Provides a reversible `down()` step. If the reverse migration is expected to be slow on production data, the docblock MUST include an `@expectedReverseSeconds <n>` annotation; the runner emits a warning at apply time when above a threshold (resolution to spec §16.3).
4. Rides the existing `bin/waaseyaa migrate` runner (no parallel runner is introduced — resolution to spec §16.2). Generated migrations adhere to the ADR-009 manifest format.

## Emitted file shape (sketch)

```php
namespace Waaseyaa\Teaching\Migrations;

use Waaseyaa\Foundation\Migration\Migration;
use Doctrine\DBAL\Connection;

/**
 * Migrate `teaching` entity from sql-blob to sql-column.
 *
 * @expectedReverseSeconds 30
 */
final class M2026_05_12_120000_TeachingToSqlColumn extends Migration
{
    public function up(Connection $conn): void { ... }
    public function down(Connection $conn): void { ... }
}
```

## Failure modes (normative)

| Condition | Exit code | Stderr message |
|---|---|---|
| Unknown entity type id | 1 | `Unknown entity type: <id>` |
| Unsupported `--target` value | 2 | `Unsupported target backend: <id>. Only sql-column is supported.` |
| Migration file already exists | 3 | `Migration <name> exists. Use --force to overwrite.` |
| Field type without §8.2 mapping | 4 | `Field <field_id> has type <type> which has no sql-column mapping. Route it to an alternate backend via FieldDefinition::storedIn(<backend>).` |

## Test surface

- Unit tests under `packages/cli/tests/` for argument parsing and exit codes.
- Integration test that generates a migration for a fixture entity type, applies it via `bin/waaseyaa migrate`, verifies `sql-column` schema + backfilled data, then reverts via `down()`.
- WP11 validation: real `teaching` migration in Minoo through this command.

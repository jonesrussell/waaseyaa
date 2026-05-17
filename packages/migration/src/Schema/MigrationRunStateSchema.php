<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Schema;

/**
 * Declarative descriptor for the `migration_run_state` table
 * (data-model.md §4.2, FR-038).
 *
 * The schema is captured once here and reused by the package migration file
 * shipped alongside it and by tests that need to build the same shape on
 * `:memory:` SQLite without going through the migrator.
 *
 * Unlike {@see MigrationIdMapSchema}, this table is **mission-internal
 * infrastructure** — not charter §5.8 stable surface. Future column or
 * index changes do not require a charter amendment; the runner and
 * {@see \Waaseyaa\Migration\MigrationRunState} repository are free to
 * evolve the storage shape together.
 *
 * Notes:
 *  - Column is `item_status` (not `status`) per
 *    `.claude/rules/shell-compatibility.md` — `status` is read-only in zsh
 *    and collides with the per-migration aggregate "state" surfaced by
 *    {@see \Waaseyaa\CLI\Command\Import\ImportStatusCommand}.
 *  - The PRIMARY KEY `(migration_id, source_id_hash)` means re-runs OVERWRITE
 *    prior outcomes for the same record; the table tracks the LATEST
 *    outcome per record.
 *  - The `(migration_id, run_id, position)` index supports the resume
 *    checkpoint query (`MAX(position)` for a `(migration_id, run_id)` pair)
 *    in a single index lookup.
 *
 * @internal — mission-internal infrastructure, not stable surface.
 *
 * @spec FR-038 — per-record progress tracking
 * @api
 */
final class MigrationRunStateSchema
{
    /**
     * Logical table name. Not prefixed — apply your install's table prefix
     * at statement-build time if you use one.
     */
    public static function tableName(): string
    {
        return 'migration_run_state';
    }

    /**
     * Ordered column definitions.
     *
     * Each entry: `['name' => string, 'sql_type' => string, 'nullable' => bool]`.
     * SQL types target SQLite (the primary CI driver) and use the
     * lowest-common-denominator types (`TEXT`, `INTEGER`) so the same DDL
     * works on the other drivers we care about (MySQL, Postgres) without
     * per-driver branching.
     *
     * @return list<array{name: string, sql_type: string, nullable: bool}>
     */
    public static function columns(): array
    {
        return [
            ['name' => 'migration_id',   'sql_type' => 'TEXT',    'nullable' => false],
            ['name' => 'source_id_hash', 'sql_type' => 'TEXT',    'nullable' => false],
            ['name' => 'run_id',         'sql_type' => 'TEXT',    'nullable' => false],
            ['name' => 'item_status',    'sql_type' => 'TEXT',    'nullable' => false],
            ['name' => 'error_code',     'sql_type' => 'TEXT',    'nullable' => true],
            ['name' => 'error_message',  'sql_type' => 'TEXT',    'nullable' => true],
            ['name' => 'position',       'sql_type' => 'INTEGER', 'nullable' => false],
            ['name' => 'updated_at',     'sql_type' => 'TEXT',    'nullable' => false],
        ];
    }

    /**
     * Composite primary key — `(migration_id, source_id_hash)`. Re-running a
     * migration overwrites the prior per-record outcome.
     *
     * @return list<string>
     */
    public static function primaryKey(): array
    {
        return ['migration_id', 'source_id_hash'];
    }

    /**
     * Secondary indexes.
     *
     * The `(migration_id, run_id, position)` index supports both the
     * resume checkpoint query (`MAX(position)` per `(migration_id, run_id)`)
     * and the latest-run lookup used by `MigrationRunState::latestRunForMigration()`.
     *
     * @return list<array{name: string, columns: list<string>, unique: bool}>
     */
    public static function indexes(): array
    {
        return [
            [
                'name' => 'migration_run_state__run',
                'columns' => ['migration_id', 'run_id', 'position'],
                'unique' => false,
            ],
        ];
    }

    /**
     * Render the `CREATE TABLE` statement (portable SQL — works on SQLite,
     * MySQL, Postgres without driver branching).
     *
     * Idempotent: emits `CREATE TABLE IF NOT EXISTS`.
     */
    public static function createTableSql(): string
    {
        $columnLines = [];
        foreach (self::columns() as $col) {
            $columnLines[] = \sprintf(
                '    %s %s %s',
                $col['name'],
                $col['sql_type'],
                $col['nullable'] ? 'NULL' : 'NOT NULL',
            );
        }
        $columnLines[] = \sprintf('    PRIMARY KEY (%s)', \implode(', ', self::primaryKey()));

        return \sprintf(
            "CREATE TABLE IF NOT EXISTS %s (\n%s\n)",
            self::tableName(),
            \implode(",\n", $columnLines),
        );
    }

    /**
     * Render the `CREATE INDEX` statements (idempotent — `IF NOT EXISTS`).
     *
     * @return list<string>
     */
    public static function createIndexSqls(): array
    {
        $sqls = [];
        foreach (self::indexes() as $index) {
            $sqls[] = \sprintf(
                'CREATE %sINDEX IF NOT EXISTS %s ON %s (%s)',
                $index['unique'] ? 'UNIQUE ' : '',
                $index['name'],
                self::tableName(),
                \implode(', ', $index['columns']),
            );
        }

        return $sqls;
    }

    /**
     * Render the `DROP TABLE` statement (idempotent).
     */
    public static function dropTableSql(): string
    {
        return \sprintf('DROP TABLE IF EXISTS %s', self::tableName());
    }
}

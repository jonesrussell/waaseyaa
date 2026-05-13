<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Schema;

/**
 * Declarative descriptor for the `migration_id_map` table (spec §8.1, FR-025).
 *
 * The schema is captured once here and reused by the package migration file
 * ({@see \Waaseyaa\Migration\Schema\MigrationIdMapSchema} consumers below)
 * and by tests that need to build the same shape on `:memory:` SQLite
 * without going through the migrator.
 *
 * The schema shape is **stable surface** — future changes require a
 * charter amendment per spec §8.1 and a data migration of every existing
 * id-map row.
 *
 * @api
 *
 * @spec FR-025 — stable id-map table
 */
final class MigrationIdMapSchema
{
    /**
     * Logical table name. Not prefixed — apply your install's table prefix at
     * statement-build time if you use one.
     */
    public static function tableName(): string
    {
        return 'migration_id_map';
    }

    /**
     * Ordered column definitions.
     *
     * Each entry: `['name' => string, 'sql_type' => string, 'nullable' => bool]`.
     * The SQL type strings target SQLite (the primary CI driver) and use the
     * lowest-common-denominator types (`TEXT`) so the same DDL works on the
     * other drivers we care about (MySQL, Postgres) without per-driver
     * branching. Width / collation tuning is a follow-up if it becomes
     * necessary.
     *
     * @return list<array{name: string, sql_type: string, nullable: bool}>
     */
    public static function columns(): array
    {
        return [
            ['name' => 'migration_id',            'sql_type' => 'TEXT', 'nullable' => false],
            ['name' => 'source_id_hash',          'sql_type' => 'TEXT', 'nullable' => false],
            ['name' => 'destination_entity_type', 'sql_type' => 'TEXT', 'nullable' => false],
            ['name' => 'destination_uuid',        'sql_type' => 'TEXT', 'nullable' => false],
            ['name' => 'last_imported_at',        'sql_type' => 'TEXT', 'nullable' => false],
            ['name' => 'last_run_id',             'sql_type' => 'TEXT', 'nullable' => false],
            ['name' => 'source_record_hash',      'sql_type' => 'TEXT', 'nullable' => false],
        ];
    }

    /**
     * Composite primary key (per spec §8.1) — `(migration_id, source_id_hash)`.
     *
     * @return list<string>
     */
    public static function primaryKey(): array
    {
        return ['migration_id', 'source_id_hash'];
    }

    /**
     * Secondary indexes (per spec §8.1).
     *
     * @return list<array{name: string, columns: list<string>, unique: bool}>
     */
    public static function indexes(): array
    {
        return [
            [
                'name' => 'migration_id_map__entity',
                'columns' => ['destination_entity_type', 'destination_uuid'],
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

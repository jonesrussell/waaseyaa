<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Migration\Schema\MigrationRunStateSchema;

/**
 * Creates the `migration_run_state` table per data-model.md §4.2 / FR-038.
 *
 * The schema shape is owned by {@see MigrationRunStateSchema}; this file only
 * applies it through the framework migrator so the table also exists on
 * minimal-boot paths (e.g. `db:init`).
 *
 * **Mission-internal infrastructure** — unlike `migration_id_map`, the
 * `migration_run_state` table is NOT on the §5.8 stable surface. Future
 * schema changes do not require a charter amendment; the runner and the
 * {@see \Waaseyaa\Migration\MigrationRunState} repository can evolve the
 * storage shape together.
 *
 * The filename's date prefix (`2026_05_13_000002_`) sorts lexicographically
 * after `2026_05_13_000001_create_migration_id_map.php` (WP04). All
 * future migrations in this package follow the same convention.
 *
 * @spec FR-038 — ship `migration_run_state` per-record progress table
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $conn = $schema->getConnection();

        $conn->executeStatement(MigrationRunStateSchema::createTableSql());

        foreach (MigrationRunStateSchema::createIndexSqls() as $indexSql) {
            $conn->executeStatement($indexSql);
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        $conn = $schema->getConnection();

        $conn->executeStatement(MigrationRunStateSchema::dropTableSql());
    }
};

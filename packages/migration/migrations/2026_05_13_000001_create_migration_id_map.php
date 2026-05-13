<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Migration\Schema\MigrationIdMapSchema;

/**
 * Creates the `migration_id_map` table per spec §8.1 / FR-025.
 *
 * The schema shape is owned by {@see MigrationIdMapSchema}; this file only
 * applies it through the framework migrator so the table also exists on
 * minimal-boot paths (e.g. `db:init`).
 *
 * **Stable surface** — the table layout is frozen. Future column or index
 * changes require a charter amendment + a data migration of existing rows.
 *
 * The filename's date prefix (`2026_05_13_000001_`) is the convention for
 * future migrations in this package — sort lexicographically before any
 * later WP-shipped migration.
 *
 * @spec FR-025 — ship `migration_id_map` on stable surface
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $conn = $schema->getConnection();

        $conn->executeStatement(MigrationIdMapSchema::createTableSql());

        foreach (MigrationIdMapSchema::createIndexSqls() as $indexSql) {
            $conn->executeStatement($indexSql);
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        $conn = $schema->getConnection();

        $conn->executeStatement(MigrationIdMapSchema::dropTableSql());
    }
};

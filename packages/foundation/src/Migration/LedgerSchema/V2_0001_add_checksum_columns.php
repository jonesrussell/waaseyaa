<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration\LedgerSchema;

use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

/**
 * Authoring record for the WP09 ledger-schema upgrade.
 *
 * Adds `checksum` and `diff_hash` (both nullable VARCHAR(64)) to the
 * `waaseyaa_migrations` table so v2 applies record their canonical
 * SHA-256 fingerprints (per spec §15 Q1 / Q2).
 *
 * **Self-application caveat (per WP09 risk note):** the ledger table
 * cannot use the standard Migrator pipeline to apply its own schema
 * changes — the row recording the apply needs the new columns to exist
 * before it can be written. The runtime path bypasses the Migrator and
 * goes through {@see \Waaseyaa\Foundation\Migration\MigrationRepository::ensureCurrentSchema()},
 * which applies the same effect idempotently via direct DDL on every
 * boot. This class exists for two reasons:
 *
 * 1. It documents the structural intent in the canonical SchemaDiff
 *    algebra so audit tooling and verify mode (WP10) can introspect
 *    "what does the ledger schema look like at v2.0001".
 * 2. Its tests lock the canonical-JSON shape and the MigrationPlan
 *    checksum, catching any accidental drift in the ColumnSpec encoding
 *    that would silently change every recorded `checksum` value.
 */
final readonly class V2_0001_add_checksum_columns implements MigrationInterfaceV2
{
    public function migrationId(): string
    {
        return 'waaseyaa/foundation:v2:ledger-add-checksum-columns';
    }

    public function package(): string
    {
        return 'waaseyaa/foundation';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function plan(): MigrationPlan
    {
        return new MigrationPlan(
            migrationId: $this->migrationId(),
            package: $this->package(),
            dependencies: [],
            root: new CompositeDiff([
                new AddColumn(
                    'waaseyaa_migrations',
                    'checksum',
                    new ColumnSpec(type: 'varchar', nullable: true, length: 64),
                ),
                new AddColumn(
                    'waaseyaa_migrations',
                    'diff_hash',
                    new ColumnSpec(type: 'varchar', nullable: true, length: 64),
                ),
            ]),
        );
    }
}

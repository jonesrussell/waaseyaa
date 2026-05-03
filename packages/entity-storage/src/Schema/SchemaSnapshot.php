<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Schema;

use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;

/**
 * In-memory snapshot of "what's currently materialised in the
 * database" for one or more tables.
 *
 * Pure DTO. The factory consumes it as the "current state" half of the
 * diff comparison; the other half is the registered field set. v1
 * (this WP) does not include a producer — tests construct snapshots by
 * hand, and verify mode (WP10 + future round-trip work) will own the
 * "load snapshot from a live DB" path.
 *
 * Shape: `tables` maps `tableName => (columnName => ColumnSpec)`.
 * Empty `tables` (or missing-table entries) means "table has no
 * materialised columns we know about" — equivalent to a fresh install.
 */
final readonly class SchemaSnapshot
{
    /**
     * @param array<string, array<string, ColumnSpec>> $tables
     */
    public function __construct(public array $tables = []) {}

    /**
     * @return array<string, ColumnSpec>
     */
    public function columnsFor(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    public function hasTable(string $table): bool
    {
        return isset($this->tables[$table]);
    }
}

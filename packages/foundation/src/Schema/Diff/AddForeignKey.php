<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Add a foreign-key constraint to an existing table.
 *
 * Per §15 Q6 (ratified 2026-05-02), the SQLite v1 compiler MUST reject
 * any FK op with the diagnostic code `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`.
 * The value type still exists for the contract — MySQL/Postgres compilers
 * (future ADR) implement it.
 */
final readonly class AddForeignKey implements SchemaDiffOp
{
    public function __construct(
        public string $table,
        public ForeignKeySpec $spec,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::AddForeignKey;
    }

    public function toCanonical(): array
    {
        return [
            'kind' => $this->kind()->value,
            'spec' => $this->spec->toCanonical(),
            'table' => $this->table,
        ];
    }
}

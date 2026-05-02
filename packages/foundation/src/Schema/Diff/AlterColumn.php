<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * In-place column shape change.
 *
 * Per §15 Q5 (ratified 2026-05-02), the SQLite v1 compiler MUST reject
 * AlterColumn at compile time with a stable diagnostic code; this value
 * type still exists so the diff algebra is complete on non-SQLite
 * dialects (planned MySQL/Postgres compilers) and so future SQLite
 * rebuild strategies can land without an algebra change.
 */
final readonly class AlterColumn implements SchemaDiffOp
{
    public function __construct(
        public string $table,
        public string $column,
        public ColumnSpec $newSpec,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::AlterColumn;
    }

    public function toCanonical(): array
    {
        return [
            'column' => $this->column,
            'kind' => $this->kind()->value,
            'new_spec' => $this->newSpec->toCanonical(),
            'table' => $this->table,
        ];
    }
}

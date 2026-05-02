<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Step;

use Waaseyaa\Foundation\Schema\Compiler\CompiledStep;

/**
 * Specialised step for "add column to existing table".
 *
 * Carries structured `table` + `column` so verify-mode readers and
 * operator diagnostics can describe the change without re-parsing SQL.
 */
final readonly class AlterTableAddColumn implements CompiledStep
{
    public function __construct(
        public string $table,
        public string $column,
        public string $sql,
    ) {}

    public function kind(): string
    {
        return 'alter_table_add_column';
    }

    public function sql(): string
    {
        return $this->sql;
    }

    public function toCanonical(): array
    {
        return [
            'column' => $this->column,
            'kind' => $this->kind(),
            'sql' => $this->sql,
            'table' => $this->table,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

final readonly class AddColumn implements SchemaDiffOp
{
    public function __construct(
        public string $table,
        public string $column,
        public ColumnSpec $spec,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::AddColumn;
    }

    public function toCanonical(): array
    {
        return [
            'column' => $this->column,
            'kind' => $this->kind()->value,
            'spec' => $this->spec->toCanonical(),
            'table' => $this->table,
        ];
    }
}

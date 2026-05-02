<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Destructive column removal.
 *
 * Default policy in the compiler/planner: blocked unless an explicit
 * danger flag is set at the plan level. The value type itself carries no
 * policy state — guards live one layer up so the algebra stays pure.
 */
final readonly class DropColumn implements SchemaDiffOp
{
    public function __construct(
        public string $table,
        public string $column,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::DropColumn;
    }

    public function toCanonical(): array
    {
        return [
            'column' => $this->column,
            'kind' => $this->kind()->value,
            'table' => $this->table,
        ];
    }
}

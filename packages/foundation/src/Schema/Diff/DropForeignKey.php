<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Drop a named foreign-key constraint.
 *
 * Anonymous FK resolution is not supported here — the planner must know
 * the constraint name. Per §15 Q6 the SQLite v1 compiler rejects this op.
 */
final readonly class DropForeignKey implements SchemaDiffOp
{
    public function __construct(
        public string $table,
        public string $name,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::DropForeignKey;
    }

    public function toCanonical(): array
    {
        return [
            'kind' => $this->kind()->value,
            'name' => $this->name,
            'table' => $this->table,
        ];
    }
}

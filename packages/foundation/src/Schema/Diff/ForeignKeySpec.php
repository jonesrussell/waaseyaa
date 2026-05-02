<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Logical foreign-key shape consumed by {@see AddForeignKey}.
 *
 * `$localColumns` and `$referencedColumns` must have the same length —
 * each local column maps to the referenced column at the same index.
 * The value type does not enforce that invariant; the planner / compiler
 * is responsible for rejecting mismatched arities with a stable diagnostic.
 *
 * `$onDelete` / `$onUpdate` accept the SQL-92 referential-action tokens
 * (`CASCADE`, `RESTRICT`, `SET NULL`, `SET DEFAULT`, `NO ACTION`). The
 * value type carries the strings verbatim; dialect support and rejection
 * is the compiler's responsibility (per §15 Q6, the SQLite v1 compiler
 * rejects any FK op with `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`).
 */
final readonly class ForeignKeySpec
{
    /**
     * @param list<string> $localColumns
     * @param list<string> $referencedColumns
     */
    public function __construct(
        public string $referencedTable,
        public array $localColumns,
        public array $referencedColumns,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
        public ?string $name = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCanonical(): array
    {
        return [
            'local_columns' => $this->localColumns,
            'name' => $this->name,
            'on_delete' => $this->onDelete,
            'on_update' => $this->onUpdate,
            'referenced_columns' => $this->referencedColumns,
            'referenced_table' => $this->referencedTable,
        ];
    }
}

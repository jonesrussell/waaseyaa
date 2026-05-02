<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Rename an existing column on an existing table.
 *
 * Per design §3.3: rename is **never** inferred from a drop+add pair. A
 * RenameColumn op exists in the diff only when the planner explicitly
 * emits one. Anything that looks like a rename but is encoded as a drop
 * followed by an add is treated as data loss + new column — this is a
 * load-bearing safety property.
 */
final readonly class RenameColumn implements SchemaDiffOp
{
    public function __construct(
        public string $table,
        public string $from,
        public string $to,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::RenameColumn;
    }

    public function toCanonical(): array
    {
        return [
            'from' => $this->from,
            'kind' => $this->kind()->value,
            'table' => $this->table,
            'to' => $this->to,
        ];
    }
}

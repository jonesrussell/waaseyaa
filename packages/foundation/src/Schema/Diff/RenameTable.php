<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Rename a table.
 *
 * Same semantics as {@see RenameColumn}: never inferred from drop+create.
 */
final readonly class RenameTable implements SchemaDiffOp
{
    public function __construct(
        public string $from,
        public string $to,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::RenameTable;
    }

    public function toCanonical(): array
    {
        return [
            'from' => $this->from,
            'kind' => $this->kind()->value,
            'to' => $this->to,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Add a (composite-column) index to an existing table.
 *
 * `$columns` is the ordered list of columns participating in the index.
 * Order is significant — `[a, b]` and `[b, a]` produce different indexes
 * with different lookup characteristics, so they hash differently.
 *
 * `$name` is optional. When null, anonymous-index resolution is the
 * compiler's responsibility (e.g. dialect-specific naming convention).
 * The value type only carries identifying fields.
 */
final readonly class AddIndex implements SchemaDiffOp
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public string $table,
        public array $columns,
        public ?string $name = null,
        public bool $unique = false,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::AddIndex;
    }

    public function toCanonical(): array
    {
        return [
            'columns' => $this->columns,
            'kind' => $this->kind()->value,
            'name' => $this->name,
            'table' => $this->table,
            'unique' => $this->unique,
        ];
    }
}

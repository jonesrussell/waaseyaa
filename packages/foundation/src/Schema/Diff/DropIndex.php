<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Diff;

/**
 * Drop an existing index.
 *
 * Either `$name` or `$columns` must be set; the resolver lives in the
 * compiler (it may need DBAL introspection to find an anonymous index by
 * its column tuple). This value type does not enforce that guard — that
 * is a planner-level concern.
 */
final readonly class DropIndex implements SchemaDiffOp
{
    /**
     * @param list<string>|null $columns
     */
    public function __construct(
        public string $table,
        public ?string $name = null,
        public ?array $columns = null,
    ) {}

    public function kind(): OpKind
    {
        return OpKind::DropIndex;
    }

    public function toCanonical(): array
    {
        return [
            'columns' => $this->columns,
            'kind' => $this->kind()->value,
            'name' => $this->name,
            'table' => $this->table,
        ];
    }
}

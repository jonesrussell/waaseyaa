<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Step;

use Waaseyaa\Foundation\Schema\Compiler\CompiledStep;

/**
 * Specialised step for "create index on existing table".
 *
 * Carries the structured shape (`table`, `name`, `columns`, `unique`) so
 * downstream readers (verify mode, dry-run JSON output, operator
 * diagnostics) describe the change without re-parsing the CREATE INDEX
 * SQL.
 */
final readonly class CreateIndex implements CompiledStep
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public string $table,
        public string $name,
        public array $columns,
        public bool $unique,
        public string $sql,
    ) {}

    public function kind(): string
    {
        return 'create_index';
    }

    public function sql(): string
    {
        return $this->sql;
    }

    public function toCanonical(): array
    {
        return [
            'columns' => $this->columns,
            'kind' => $this->kind(),
            'name' => $this->name,
            'sql' => $this->sql,
            'table' => $this->table,
            'unique' => $this->unique,
        ];
    }
}

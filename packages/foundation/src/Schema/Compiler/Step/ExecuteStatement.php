<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Step;

use Waaseyaa\Foundation\Schema\Compiler\CompiledStep;

/**
 * A generic SQL statement with no specialised verify-mode metadata.
 *
 * Used by translators when the executable surface is a single SQL
 * statement and there is no semantic shape worth lifting into its own
 * DTO (e.g. RENAME COLUMN, RENAME TABLE on SQLite). Translators that
 * carry structured metadata (added column, created index) MUST use a
 * specialised step DTO instead so verify-mode readers don't have to
 * re-parse SQL.
 */
final readonly class ExecuteStatement implements CompiledStep
{
    public function __construct(public string $sql) {}

    public function kind(): string
    {
        return 'execute_statement';
    }

    public function sql(): string
    {
        return $this->sql;
    }

    public function toCanonical(): array
    {
        return [
            'kind' => $this->kind(),
            'sql' => $this->sql,
        ];
    }
}

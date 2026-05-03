<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;

/**
 * `AlterColumn` rejected by the SQLite compiler per §15 Q5.
 *
 * SQLite's `ALTER TABLE` cannot change a column's type or nullability
 * in place; the only safe path is a table-rebuild (CREATE NEW + COPY +
 * DROP OLD + RENAME), which is destructive and intricate. v1 of the
 * SQLite compiler refuses the op rather than silently shipping a
 * rebuild plan; future ADRs will introduce the rebuild strategy with
 * explicit operator consent.
 *
 * The exception is filed under the platform-neutral `Validation/`
 * namespace because the *concept* (rejecting an op family) is shared,
 * but its diagnostic code lives in {@see SqliteDiagnosticCode} because
 * the gate is SQLite-specific. MySQL / Postgres compilers handle
 * `AlterColumn` natively and will not throw this.
 */
final class AlterColumnUnsupportedException extends ValidationException
{
    public static function forColumn(string $table, string $column): self
    {
        return new self(
            SqliteDiagnosticCode::AlterColumnUnsupportedSqliteV1->value,
            sprintf(
                'AlterColumn on "%s"."%s" is not supported on SQLite in v1. Split as drop+add (with PlanPolicy(allowDestructive: true) and an explicit data-migration plan) or wait for the SQLite table-rebuild ADR.',
                $table,
                $column,
            ),
        );
    }
}

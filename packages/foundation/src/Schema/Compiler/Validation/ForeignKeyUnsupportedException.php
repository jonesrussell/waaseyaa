<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;

/**
 * Foreign-key op rejected by the SQLite compiler per §15 Q6.
 *
 * SQLite cannot add or drop a foreign-key constraint on an existing
 * table without a full table rebuild; FKs are part of the CREATE TABLE
 * statement only. v1 of the SQLite compiler refuses both
 * `AddForeignKey` and `DropForeignKey`. Future MySQL / Postgres
 * compilers (separate ADR) implement these natively.
 *
 * Diagnostic code lives in {@see SqliteDiagnosticCode} because the gate
 * is SQLite-specific.
 */
final class ForeignKeyUnsupportedException extends ValidationException
{
    public static function forAdd(string $table, ?string $constraintName): self
    {
        return new self(
            SqliteDiagnosticCode::ForeignKeyUnsupportedSqliteV1->value,
            sprintf(
                'AddForeignKey on table "%s"%s is not supported on SQLite in v1. Foreign keys must be declared at table-creation time on SQLite; cross-dialect FK ops will land with the MySQL / Postgres compilers.',
                $table,
                $constraintName !== null ? sprintf(' (constraint "%s")', $constraintName) : '',
            ),
        );
    }

    public static function forDrop(string $table, string $constraintName): self
    {
        return new self(
            SqliteDiagnosticCode::ForeignKeyUnsupportedSqliteV1->value,
            sprintf(
                'DropForeignKey on table "%s" (constraint "%s") is not supported on SQLite in v1. SQLite cannot drop a constraint without a full table rebuild; cross-dialect FK ops will land with the MySQL / Postgres compilers.',
                $table,
                $constraintName,
            ),
        );
    }
}

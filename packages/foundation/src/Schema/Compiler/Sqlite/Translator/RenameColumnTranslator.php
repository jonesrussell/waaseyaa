<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCapabilities;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompilerException;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;
use Waaseyaa\Foundation\Schema\Compiler\Step\ExecuteStatement;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;

/**
 * Translate {@see RenameColumn} into a SQLite ALTER TABLE step.
 *
 * Output shape: `ALTER TABLE "<table>" RENAME COLUMN "<from>" TO "<to>"`.
 *
 * **Capability gate:** SQLite added column rename in 3.25 (2018-09-15).
 * On older runtimes the translator throws {@see SqliteCompilerException}
 * with code {@see SqliteDiagnosticCode::RenameColumnUnsupportedSqliteLt325}.
 * The error path is part of the public diagnostic contract — the code
 * string MUST NOT change.
 */
final class RenameColumnTranslator
{
    public static function translate(RenameColumn $op, SqliteCapabilities $capabilities): ExecuteStatement
    {
        if (! $capabilities->supportsRenameColumn) {
            throw new SqliteCompilerException(
                SqliteDiagnosticCode::RenameColumnUnsupportedSqliteLt325,
                sprintf(
                    'SQLite %s does not support ALTER TABLE … RENAME COLUMN (added in 3.25). Cannot rename %s.%s → %s.',
                    $capabilities->version,
                    $op->table,
                    $op->from,
                    $op->to,
                ),
            );
        }

        $sql = sprintf(
            'ALTER TABLE %s RENAME COLUMN %s TO %s',
            SqliteIdentifier::quote($op->table),
            SqliteIdentifier::quote($op->from),
            SqliteIdentifier::quote($op->to),
        );

        return new ExecuteStatement($sql);
    }
}

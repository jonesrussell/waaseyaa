<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

use Waaseyaa\Foundation\Schema\Compiler\Step\ExecuteStatement;
use Waaseyaa\Foundation\Schema\Diff\RenameTable;

/**
 * Translate {@see RenameTable} into a SQLite ALTER TABLE step.
 *
 * Output shape: `ALTER TABLE "<from>" RENAME TO "<to>"`.
 *
 * Always supported on every SQLite ≥ 3.0 — no capability gate needed.
 */
final class RenameTableTranslator
{
    public static function translate(RenameTable $op): ExecuteStatement
    {
        $sql = sprintf(
            'ALTER TABLE %s RENAME TO %s',
            SqliteIdentifier::quote($op->from),
            SqliteIdentifier::quote($op->to),
        );

        return new ExecuteStatement($sql);
    }
}

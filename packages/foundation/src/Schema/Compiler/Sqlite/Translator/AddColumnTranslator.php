<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

use Waaseyaa\Foundation\Schema\Compiler\Step\AlterTableAddColumn;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;

/**
 * Translate {@see AddColumn} into a SQLite ALTER TABLE step.
 *
 * Output shape: `ALTER TABLE "<table>" ADD COLUMN "<col>" <type-sql>
 * [NOT NULL] [DEFAULT <literal>]`.
 *
 * SQLite caveat: `ALTER TABLE … ADD COLUMN` cannot add a NOT NULL
 * column without a DEFAULT (existing rows would have nothing to fill).
 * The translator does not validate this — it produces the SQL the
 * caller asked for; SQLite raises the error at apply time. WP05's
 * validation gates will catch this earlier with a stable diagnostic
 * code.
 */
final class AddColumnTranslator
{
    public static function translate(AddColumn $op): AlterTableAddColumn
    {
        $sql = sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            SqliteIdentifier::quote($op->table),
            SqliteIdentifier::quote($op->column),
            SqliteColumnType::render($op->spec),
        );

        if (! $op->spec->nullable) {
            $sql .= ' NOT NULL';
        }

        if ($op->spec->default !== null) {
            $sql .= ' DEFAULT ' . SqliteIdentifier::literal($op->spec->default);
        }

        return new AlterTableAddColumn(
            table: $op->table,
            column: $op->column,
            sql: $sql,
        );
    }
}

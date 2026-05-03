<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

use Waaseyaa\Foundation\Schema\Compiler\Validation\AlterColumnUnsupportedException;
use Waaseyaa\Foundation\Schema\Diff\AlterColumn;

/**
 * Translates {@see AlterColumn} into a hard rejection per §15 Q5.
 *
 * SQLite cannot change a column's type or nullability in place. The v1
 * compiler refuses the op rather than silently shipping a destructive
 * table-rebuild. See {@see AlterColumnUnsupportedException}.
 *
 * The "translator" never returns — it is the gate. A future WP that
 * introduces the table-rebuild strategy will replace this class with a
 * real translator.
 */
final class AlterColumnTranslator
{
    /**
     * @return never
     */
    public static function translate(AlterColumn $op): never
    {
        throw AlterColumnUnsupportedException::forColumn($op->table, $op->column);
    }
}

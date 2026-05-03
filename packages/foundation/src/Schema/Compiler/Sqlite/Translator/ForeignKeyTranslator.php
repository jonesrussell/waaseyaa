<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

use Waaseyaa\Foundation\Schema\Compiler\Validation\ForeignKeyUnsupportedException;
use Waaseyaa\Foundation\Schema\Diff\AddForeignKey;
use Waaseyaa\Foundation\Schema\Diff\DropForeignKey;

/**
 * Translates {@see AddForeignKey} / {@see DropForeignKey} into a hard
 * rejection per §15 Q6.
 *
 * SQLite cannot add or drop foreign-key constraints on existing tables
 * without a full table rebuild. v1 of the SQLite compiler refuses both
 * ops; future MySQL / Postgres compilers (separate ADR) implement them
 * natively. See {@see ForeignKeyUnsupportedException}.
 *
 * The "translator" never returns — it is the gate.
 */
final class ForeignKeyTranslator
{
    /**
     * @return never
     */
    public static function translateAdd(AddForeignKey $op): never
    {
        throw ForeignKeyUnsupportedException::forAdd(
            table: $op->table,
            constraintName: $op->spec->name,
        );
    }

    /**
     * @return never
     */
    public static function translateDrop(DropForeignKey $op): never
    {
        throw ForeignKeyUnsupportedException::forDrop(
            table: $op->table,
            constraintName: $op->name,
        );
    }
}

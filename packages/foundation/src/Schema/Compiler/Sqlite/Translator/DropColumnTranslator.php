<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

use Waaseyaa\Foundation\Schema\Compiler\Step\ExecuteStatement;
use Waaseyaa\Foundation\Schema\Compiler\Validation\DestructiveOpBlockedException;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;

/**
 * Translate {@see DropColumn} into a SQLite `ALTER TABLE … DROP COLUMN`
 * step, gated by {@see PlanPolicy::$allowDestructive}.
 *
 * **Policy gate:** when `$policy->allowDestructive === false` (default)
 * the translator throws {@see DestructiveOpBlockedException} with the
 * platform-neutral `DESTRUCTIVE_OP_BLOCKED` code. Operators must pass
 * `PlanPolicy(allowDestructive: true)` to acknowledge data loss.
 *
 * **Version compatibility:** SQLite added `DROP COLUMN` in 3.35
 * (2021-03-12). On older runtimes the SQL emitted here will fail at
 * apply time with SQLite's own error. Per WP05 risk note: "v1's
 * contract is destructive is blocked OR explicitly accepted with a
 * warning that you're on your own for SQLite-version compatibility."
 * No compile-time version gate is added; future ADRs may introduce a
 * table-rebuild fallback for pre-3.35 runtimes.
 */
final class DropColumnTranslator
{
    public static function translate(DropColumn $op, PlanPolicy $policy): ExecuteStatement
    {
        if (! $policy->allowDestructive) {
            throw DestructiveOpBlockedException::forOp(
                opKind: $op->kind()->value,
                table: $op->table,
                detail: sprintf('Column "%s" would be permanently removed.', $op->column),
            );
        }

        $sql = sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            SqliteIdentifier::quote($op->table),
            SqliteIdentifier::quote($op->column),
        );

        return new ExecuteStatement($sql);
    }
}

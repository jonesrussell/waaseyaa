<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator;

use Waaseyaa\Foundation\Schema\Compiler\Step\ExecuteStatement;
use Waaseyaa\Foundation\Schema\Compiler\Validation\DestructiveOpBlockedException;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Diff\DropIndex;

/**
 * Translate {@see DropIndex} into a SQLite `DROP INDEX` step, gated by
 * {@see PlanPolicy::$allowDestructive}.
 *
 * SQLite's `DROP INDEX` does not require the table — index names are
 * unique within a schema. The {@see DropIndex::$columns} hint is ignored
 * here (anonymous-by-columns resolution is a verify-mode / introspection
 * concern out of scope for v1); the op MUST carry an explicit `$name`,
 * else this translator rejects with a structured error.
 */
final class DropIndexTranslator
{
    public static function translate(DropIndex $op, PlanPolicy $policy): ExecuteStatement
    {
        if (! $policy->allowDestructive) {
            throw DestructiveOpBlockedException::forOp(
                opKind: $op->kind()->value,
                table: $op->table,
                detail: $op->name !== null
                    ? sprintf('Index "%s" would be removed.', $op->name)
                    : 'Index would be removed.',
            );
        }

        if ($op->name === null) {
            throw new \InvalidArgumentException(
                'DropIndex requires an explicit name in v1; anonymous-by-columns resolution lives in verify mode (WP10).',
            );
        }

        $sql = sprintf(
            'DROP INDEX %s',
            SqliteIdentifier::quote($op->name),
        );

        return new ExecuteStatement($sql);
    }
}

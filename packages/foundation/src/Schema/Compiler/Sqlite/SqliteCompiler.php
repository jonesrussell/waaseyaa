<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite;

use Waaseyaa\Foundation\Schema\Compiler\CompiledMigrationPlan;
use Waaseyaa\Foundation\Schema\Compiler\CompiledStep;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\AddColumnTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\AddIndexTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\AlterColumnTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\DropColumnTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\DropIndexTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\ForeignKeyTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\RenameColumnTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\RenameTableTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Validation\OrderingValidator;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Compiler\Validation\UnknownOpKindException;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\AddForeignKey;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;
use Waaseyaa\Foundation\Schema\Diff\AlterColumn;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\DropForeignKey;
use Waaseyaa\Foundation\Schema\Diff\DropIndex;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;
use Waaseyaa\Foundation\Schema\Diff\RenameTable;
use Waaseyaa\Foundation\Schema\Diff\SchemaDiffOp;

/**
 * SQLite compiler — pure function `compile(CompositeDiff, PlanPolicy):
 * CompiledMigrationPlan`.
 *
 * **Op coverage (post-WP05):**
 *
 * | Kind             | Behaviour                                          |
 * |------------------|----------------------------------------------------|
 * | `AddColumn`      | Translates to `ALTER TABLE … ADD COLUMN`.          |
 * | `AddIndex`       | Translates to `CREATE [UNIQUE] INDEX`.             |
 * | `RenameColumn`   | Translates to `RENAME COLUMN` on SQLite ≥ 3.25.    |
 * | `RenameTable`    | Translates to `RENAME TO`.                         |
 * | `AlterColumn`    | Rejected with `ALTER_COLUMN_UNSUPPORTED_SQLITE_V1` (Q5). |
 * | `DropColumn`     | Gated by `PlanPolicy.allowDestructive`; if accepted, emits `DROP COLUMN`. |
 * | `DropIndex`      | Gated by `PlanPolicy.allowDestructive`; if accepted, emits `DROP INDEX`. |
 * | `AddForeignKey`  | Rejected with `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1` (Q6). |
 * | `DropForeignKey` | Rejected with `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1` (Q6). |
 * | (anything else)  | Rejected with `UNKNOWN_OP_KIND` from validation.   |
 *
 * **Pipeline order:**
 *
 * 1. {@see OrderingValidator} walks the composite once and rejects
 *    same-composite ordering bugs (forward column references, rename
 *    collisions, duplicate adds) with `ILLEGAL_OP_ORDER`.
 * 2. Each op dispatches through a typed `match` to its translator.
 * 3. Translators consult {@see SqliteCapabilities} (rename version
 *    gate) and {@see PlanPolicy} (destructive gate) and either return a
 *    {@see CompiledStep} or throw a structured validation exception.
 *
 * **Determinism contract:** same `CompositeDiff` + same compiler version
 * + same {@see SqliteCapabilities} + same {@see PlanPolicy} ⇒
 * byte-identical step ordering and byte-identical
 * {@see CompiledMigrationPlan::diffHash()}.
 *
 * **No DB I/O.** Compiler is a pure translation function — no
 * connections, no introspection. WP08 / WP10 own database touchpoints.
 *
 * **No caller-supplied SQL.** Translators are the only code that
 * constructs {@see CompiledStep} instances. Callers cannot smuggle raw
 * SQL through the diff layer; the only path is via a {@see SchemaDiffOp}
 * value type.
 */
final readonly class SqliteCompiler
{
    public function __construct(
        public SqliteCapabilities $capabilities,
        private OrderingValidator $orderingValidator = new OrderingValidator(),
    ) {}

    /**
     * Convenience factory that mirrors the `for(string $version)` shape
     * suggested in the WP04 plan.
     */
    public static function forVersion(string $sqliteVersion): self
    {
        return new self(SqliteCapabilities::forVersion($sqliteVersion));
    }

    public function compile(CompositeDiff $diff, PlanPolicy $policy = new PlanPolicy()): CompiledMigrationPlan
    {
        $this->orderingValidator->validate($diff);

        $steps = [];
        foreach ($diff->ops as $op) {
            $steps[] = $this->compileOp($op, $policy);
        }

        return new CompiledMigrationPlan($steps);
    }

    private function compileOp(SchemaDiffOp $op, PlanPolicy $policy): CompiledStep
    {
        return match (true) {
            $op instanceof AddColumn => AddColumnTranslator::translate($op),
            $op instanceof AddIndex => AddIndexTranslator::translate($op),
            $op instanceof RenameColumn => RenameColumnTranslator::translate($op, $this->capabilities),
            $op instanceof RenameTable => RenameTableTranslator::translate($op),
            $op instanceof AlterColumn => AlterColumnTranslator::translate($op),
            $op instanceof DropColumn => DropColumnTranslator::translate($op, $policy),
            $op instanceof DropIndex => DropIndexTranslator::translate($op, $policy),
            $op instanceof AddForeignKey => ForeignKeyTranslator::translateAdd($op),
            $op instanceof DropForeignKey => ForeignKeyTranslator::translateDrop($op),
            default => throw UnknownOpKindException::for($op->kind()->value),
        };
    }
}

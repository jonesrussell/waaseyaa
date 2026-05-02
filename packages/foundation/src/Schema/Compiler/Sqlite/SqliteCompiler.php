<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite;

use Waaseyaa\Foundation\Schema\Compiler\CompiledMigrationPlan;
use Waaseyaa\Foundation\Schema\Compiler\CompiledStep;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\AddColumnTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\AddIndexTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\RenameColumnTranslator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\RenameTableTranslator;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\OpKind;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;
use Waaseyaa\Foundation\Schema\Diff\RenameTable;
use Waaseyaa\Foundation\Schema\Diff\SchemaDiffOp;

/**
 * SQLite compiler — pure function `compile(CompositeDiff): CompiledMigrationPlan`.
 *
 * **WP04 scope:** additive ops only — `AddColumn`, `AddIndex`,
 * `RenameColumn` (gated on SQLite ≥ 3.25), `RenameTable`. The full
 * validation gates and capability matrix come in WP05; this WP focuses
 * on the additive happy path.
 *
 * **Determinism contract:** same `CompositeDiff` + same compiler version
 * + same {@see SqliteCapabilities} ⇒ byte-identical step ordering and
 * byte-identical {@see CompiledMigrationPlan::diffHash()}. Locked by the
 * golden tests in `tests/Unit/Schema/Compiler/Sqlite/`.
 *
 * **No DB I/O.** The compiler never opens a SQLite connection; it is a
 * pure translation function. Any test that opens a SQLite handle here
 * is a smell — that belongs to WP06 / WP08 (executor + integration).
 *
 * **No `caller-supplied` SQL.** The compiler is the only thing that
 * constructs {@see CompiledStep} instances. Callers cannot smuggle raw
 * SQL through the diff layer; the only path is via a {@see SchemaDiffOp}
 * value type.
 *
 * **Op kinds not yet implemented in WP04:** `AlterColumn`, `DropColumn`,
 * `DropIndex`, `AddForeignKey`, `DropForeignKey` raise
 * {@see SqliteCompilerException} with diagnostic code
 * {@see SqliteDiagnosticCode::OperationNotImplemented}. WP05 replaces
 * these with the proper validation-gate codes from §15 Q5 / Q6.
 */
final readonly class SqliteCompiler
{
    public function __construct(public SqliteCapabilities $capabilities) {}

    /**
     * Convenience factory that mirrors the `for(string $version)` shape
     * suggested in the WP04 plan.
     */
    public static function forVersion(string $sqliteVersion): self
    {
        return new self(SqliteCapabilities::forVersion($sqliteVersion));
    }

    public function compile(CompositeDiff $diff): CompiledMigrationPlan
    {
        $steps = [];

        foreach ($diff->ops as $op) {
            $steps[] = $this->compileOp($op);
        }

        return new CompiledMigrationPlan($steps);
    }

    private function compileOp(SchemaDiffOp $op): CompiledStep
    {
        return match (true) {
            $op instanceof AddColumn => AddColumnTranslator::translate($op),
            $op instanceof AddIndex => AddIndexTranslator::translate($op),
            $op instanceof RenameColumn => RenameColumnTranslator::translate($op, $this->capabilities),
            $op instanceof RenameTable => RenameTableTranslator::translate($op),
            default => throw $this->notImplemented($op->kind()),
        };
    }

    private function notImplemented(OpKind $kind): SqliteCompilerException
    {
        return new SqliteCompilerException(
            SqliteDiagnosticCode::OperationNotImplemented,
            sprintf(
                'SQLite compiler does not yet implement op kind "%s" (WP05 will add the proper validation gate).',
                $kind->value,
            ),
        );
    }
}

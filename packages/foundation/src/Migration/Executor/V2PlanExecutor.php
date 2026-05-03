<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration\Executor;

use Doctrine\DBAL\Connection;
use Waaseyaa\Foundation\Schema\Compiler\CompiledMigrationPlan;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

/**
 * Executes a v2 {@see MigrationPlan} by compiling it through the WP04
 * SQLite compiler and issuing each {@see \Waaseyaa\Foundation\Schema\Compiler\CompiledStep}'s
 * SQL on the live DBAL connection.
 *
 * Returns the {@see CompiledMigrationPlan} so the caller can record its
 * {@see CompiledMigrationPlan::diffHash()} in the ledger (per WP09 / Q2).
 *
 * **Empty-plan contract:** an empty {@see MigrationPlan} compiles to a
 * {@see CompiledMigrationPlan} with zero steps. The loop runs zero
 * iterations; the empty-plan diff_hash is still a valid stable hash
 * (SHA-256 of `{"steps":[]}`) and is recorded in the ledger by the
 * caller. Per spec §15 Q3 this is a successful no-op apply.
 *
 * **Transaction boundary:** the executor is transaction-agnostic. The
 * Migrator wraps each node's apply (compile + execute + ledger record)
 * in `Connection::transactional()` so a v2 step failure rolls back both
 * SQL and ledger.
 *
 * **WP04 surface only:** v1 hard-codes {@see SqliteCompiler}. Future
 * MySQL / Postgres compilers will land behind a platform-resolution
 * interface; until then the dispatch lives one layer up in the Migrator.
 */
final readonly class V2PlanExecutor
{
    public function __construct(
        private Connection $connection,
        private SqliteCompiler $compiler,
    ) {}

    public function execute(MigrationPlan $plan, PlanPolicy $policy): CompiledMigrationPlan
    {
        $compiled = $this->compiler->compile($plan->root, $policy);

        foreach ($compiled->steps as $step) {
            $this->connection->executeStatement($step->sql());
        }

        return $compiled;
    }
}

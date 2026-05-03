<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration\Executor;

use Doctrine\DBAL\Connection;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

/**
 * Executes a v2 {@see MigrationPlan} by compiling it through the WP04
 * SQLite compiler and issuing each {@see \Waaseyaa\Foundation\Schema\Compiler\CompiledStep}'s
 * SQL on the live DBAL connection.
 *
 * **Empty-plan contract:** {@see MigrationPlan::isEmpty()} short-circuits
 * before the compile call. Per spec §15 Q3 the empty plan is a valid
 * apply that writes the ledger row but emits zero SQL — see
 * {@see \Waaseyaa\Foundation\Migration\Migrator}.
 *
 * **Transaction boundary:** the executor itself is transaction-agnostic.
 * The Migrator wraps each node's apply (compile + execute + ledger
 * record) in `Connection::transactional()` so a v2 step failure rolls
 * back both SQL and ledger.
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

    public function execute(MigrationPlan $plan, PlanPolicy $policy): void
    {
        if ($plan->isEmpty()) {
            return;
        }

        $compiled = $this->compiler->compile($plan->root, $policy);

        foreach ($compiled->steps as $step) {
            $this->connection->executeStatement($step->sql());
        }
    }
}

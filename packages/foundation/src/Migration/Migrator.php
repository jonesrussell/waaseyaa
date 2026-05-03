<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration;

use Doctrine\DBAL\Connection;
use Waaseyaa\Foundation\Migration\Dag\MigrationGraph;
use Waaseyaa\Foundation\Migration\Dag\MigrationKind;
use Waaseyaa\Foundation\Migration\Dag\MigrationNode;
use Waaseyaa\Foundation\Migration\Executor\V2PlanExecutor;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;

/**
 * Applies pending migrations.
 *
 * Post-WP06, the Migrator routes both legacy and v2 migrations through a
 * single {@see MigrationGraph}: one batch per `run()`, one transaction
 * per node, deterministic order via Q4's `(package ASC, id ASC)`
 * tie-break. Empty v2 plans write a ledger row and execute zero SQL.
 *
 * **Failure semantics (intentional, locked by tests):** a SQL or compile
 * failure inside a node aborts that node's transaction (rollback of both
 * SQL and ledger row for the failing node). Prior nodes in the same
 * batch retain their ledger rows. This matches pre-WP06 legacy
 * behaviour — there is no "atomic batch" guarantee across nodes.
 *
 * **Backward compatibility:** the legacy `run(array $migrations)` shape
 * still works. v2 callers pass a list of {@see MigrationInterfaceV2}
 * instances as the second argument; the optional third argument carries
 * a {@see PlanPolicy} for the destructive-op gate.
 */
final class Migrator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MigrationRepository $repository,
        private readonly ?V2PlanExecutor $v2Executor = null,
    ) {}

    /**
     * @param array<string, array<string, Migration>> $migrations    package => [name => Migration]
     * @param list<MigrationInterfaceV2>              $v2Migrations  v2 migrations (optional)
     * @param PlanPolicy                              $policy        applied to every v2 plan in this run
     */
    public function run(
        array $migrations,
        array $v2Migrations = [],
        PlanPolicy $policy = new PlanPolicy(),
    ): MigrationResult {
        $nodes = $this->buildNodes($migrations, $v2Migrations);

        if ($v2Migrations !== [] && $this->v2Executor === null) {
            throw new \LogicException(
                'Migrator received v2 migrations but no V2PlanExecutor was injected. Construct the Migrator with a non-null V2PlanExecutor to enable the v2 dispatch path.',
            );
        }

        $ordered = MigrationGraph::build($nodes)->topologicalOrder();
        $batch = $this->repository->getLastBatchNumber() + 1;
        $ran = [];

        foreach ($ordered as $node) {
            if ($this->repository->hasRun($node->id)) {
                continue;
            }

            $this->applyNode($node, $batch, $policy);
            $ran[] = $node->id;
        }

        return new MigrationResult(count($ran), $ran);
    }

    /**
     * @param array<string, array<string, Migration>> $migrations
     */
    public function rollback(array $migrations): MigrationResult
    {
        $batch = $this->repository->getLastBatchNumber();
        if ($batch === 0) {
            return new MigrationResult(0);
        }

        $records = $this->repository->getByBatch($batch);
        $flat = $this->flattenMigrations($migrations);
        $rolledBack = [];

        foreach ($records as $record) {
            $name = $record['migration'];
            $this->connection->transactional(function () use ($flat, $name): void {
                if (isset($flat[$name])) {
                    $schema = new SchemaBuilder($this->connection);
                    $flat[$name]->down($schema);
                }
                $this->repository->remove($name);
            });
            $rolledBack[] = $name;
        }

        return new MigrationResult(count($rolledBack), $rolledBack);
    }

    /**
     * @param array<string, array<string, Migration>> $migrations
     * @param list<MigrationInterfaceV2>              $v2Migrations
     * @return array{pending: list<string>, completed: list<array{migration: string, package: string, batch: int}>}
     */
    public function status(array $migrations, array $v2Migrations = []): array
    {
        $completedDetails = $this->repository->getCompletedWithDetails();
        $completedNames = array_column($completedDetails, 'migration');

        $allIds = array_keys($this->flattenMigrations($migrations));
        foreach ($v2Migrations as $v2) {
            $allIds[] = $v2->migrationId();
        }

        $pending = array_values(array_diff($allIds, $completedNames));

        return ['pending' => $pending, 'completed' => $completedDetails];
    }

    /**
     * @param array<string, array<string, Migration>> $migrations
     * @param list<MigrationInterfaceV2>              $v2Migrations
     * @return list<MigrationNode>
     */
    private function buildNodes(array $migrations, array $v2Migrations): array
    {
        $nodes = [];

        foreach ($migrations as $package => $packageMigrations) {
            foreach ($packageMigrations as $name => $migration) {
                $nodes[] = MigrationNode::fromLegacy($name, $package, $migration);
            }
        }

        foreach ($v2Migrations as $v2) {
            $nodes[] = MigrationNode::fromV2($v2);
        }

        return $nodes;
    }

    private function applyNode(MigrationNode $node, int $batch, PlanPolicy $policy): void
    {
        match ($node->kind) {
            MigrationKind::Legacy => $this->applyLegacy($node, $batch),
            MigrationKind::V2 => $this->applyV2($node, $batch, $policy),
        };
    }

    private function applyLegacy(MigrationNode $node, int $batch): void
    {
        $migration = $node->legacy;
        if ($migration === null) {
            // MigrationNode invariants prevent this, but PHPStan needs the guard.
            throw new \LogicException(sprintf('Legacy node "%s" has no source migration.', $node->id));
        }

        $schema = new SchemaBuilder($this->connection);
        $this->connection->transactional(function () use ($migration, $schema, $node, $batch): void {
            $migration->up($schema);
            $this->repository->record($node->id, $node->package, $batch);
        });
    }

    private function applyV2(MigrationNode $node, int $batch, PlanPolicy $policy): void
    {
        $migration = $node->v2;
        $executor = $this->v2Executor;
        if ($migration === null || $executor === null) {
            throw new \LogicException(sprintf('V2 node "%s" cannot apply: missing source or executor.', $node->id));
        }

        $this->connection->transactional(function () use ($executor, $migration, $node, $batch, $policy): void {
            $executor->execute($migration->plan(), $policy);
            $this->repository->record($node->id, $node->package, $batch);
        });
    }

    /**
     * @param array<string, array<string, Migration>> $migrations
     * @return array<string, Migration>
     */
    private function flattenMigrations(array $migrations): array
    {
        $flat = [];
        foreach ($migrations as $packageMigrations) {
            foreach ($packageMigrations as $name => $migration) {
                $flat[$name] = $migration;
            }
        }
        return $flat;
    }
}

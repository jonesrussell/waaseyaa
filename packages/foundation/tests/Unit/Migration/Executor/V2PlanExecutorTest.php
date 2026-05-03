<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Migration\Executor;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\Executor\V2PlanExecutor;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

#[CoversClass(V2PlanExecutor::class)]
final class V2PlanExecutorTest extends TestCase
{
    private \Doctrine\DBAL\Connection $connection;
    private V2PlanExecutor $executor;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        // Seed a base table so AddColumn has something to alter.
        $this->connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $this->executor = new V2PlanExecutor(
            $this->connection,
            SqliteCompiler::forVersion('3.40.0'),
        );
    }

    #[Test]
    public function executesEverySqlStatementFromCompiledPlan(): void
    {
        $plan = new MigrationPlan(
            migrationId: 'waaseyaa/test:v2:add-archived-at',
            package: 'waaseyaa/test',
            dependencies: [],
            root: new CompositeDiff([
                new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
            ]),
        );

        $this->executor->execute($plan, new PlanPolicy());

        $columns = array_column(
            $this->connection->executeQuery('PRAGMA table_info("widgets")')->fetchAllAssociative(),
            'name',
        );
        self::assertContains('archived_at', $columns);
    }

    #[Test]
    public function emptyPlanShortCircuitsAndIssuesNoSql(): void
    {
        $plan = new MigrationPlan(
            migrationId: 'waaseyaa/test:v2:noop',
            package: 'waaseyaa/test',
            dependencies: [],
            root: CompositeDiff::empty(),
        );

        $beforeColumns = array_column(
            $this->connection->executeQuery('PRAGMA table_info("widgets")')->fetchAllAssociative(),
            'name',
        );

        $this->executor->execute($plan, new PlanPolicy());

        $afterColumns = array_column(
            $this->connection->executeQuery('PRAGMA table_info("widgets")')->fetchAllAssociative(),
            'name',
        );
        self::assertSame($beforeColumns, $afterColumns);
    }

    #[Test]
    public function policyIsForwardedToCompiler(): void
    {
        // Default policy blocks DropColumn → executor surfaces the
        // platform-neutral DESTRUCTIVE_OP_BLOCKED via the compiler.
        $plan = new MigrationPlan(
            migrationId: 'waaseyaa/test:v2:drop-archived',
            package: 'waaseyaa/test',
            dependencies: [],
            root: new CompositeDiff([
                new \Waaseyaa\Foundation\Schema\Diff\DropColumn('widgets', 'id'),
            ]),
        );

        $this->expectException(\Waaseyaa\Foundation\Schema\Compiler\Validation\DestructiveOpBlockedException::class);

        $this->executor->execute($plan, new PlanPolicy());
    }
}

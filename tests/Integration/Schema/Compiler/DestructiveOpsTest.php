<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Schema\Compiler;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\Executor\V2PlanExecutor;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\Migrator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;
use Waaseyaa\Foundation\Schema\Compiler\Validation\DestructiveOpBlockedException;
use Waaseyaa\Foundation\Schema\Compiler\Validation\ForeignKeyUnsupportedException;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Compiler\Validation\ValidationDiagnosticCode;
use Waaseyaa\Foundation\Schema\Diff\AddForeignKey;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\DropIndex;
use Waaseyaa\Foundation\Schema\Diff\ForeignKeySpec;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

/**
 * Mission #529 / WP08 / T046: destructive op gates.
 *
 * Locks the WP05 policy contract: drop ops require explicit
 * `PlanPolicy(allowDestructive: true)`; FK ops are always rejected on
 * SQLite v1 regardless of policy. Stable diagnostic codes participate
 * in the operator surface and must not change.
 */
#[CoversNothing]
final class DestructiveOpsTest extends TestCase
{
    #[Test]
    public function dropColumnUnderDefaultPolicyIsBlocked(): void
    {
        [, , $migrator] = self::harness();

        $thrown = null;
        try {
            $migrator->run([], [self::v2('waaseyaa/test:v2:drop', new CompositeDiff([
                new DropColumn('widgets', 'status'),
            ]))]);
        } catch (DestructiveOpBlockedException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(ValidationDiagnosticCode::DestructiveOpBlocked->value, $thrown->diagnosticCode);
        self::assertSame('drop_column', $thrown->opKind);
    }

    #[Test]
    public function dropColumnWithDestructivePolicyApplies(): void
    {
        [$connection, , $migrator] = self::harness();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY, status TEXT)');

        $migrator->run(
            [],
            [self::v2('waaseyaa/test:v2:drop', new CompositeDiff([
                new DropColumn('widgets', 'status'),
            ]))],
            new PlanPolicy(allowDestructive: true),
        );

        $cols = array_column(
            $connection->executeQuery('PRAGMA table_info(widgets)')->fetchAllAssociative(),
            'name',
        );
        self::assertNotContains('status', $cols);
    }

    #[Test]
    public function dropIndexUnderDefaultPolicyIsBlocked(): void
    {
        [, , $migrator] = self::harness();

        $thrown = null;
        try {
            $migrator->run([], [self::v2('waaseyaa/test:v2:drop-idx', new CompositeDiff([
                new DropIndex('widgets', 'idx_widgets_status'),
            ]))]);
        } catch (DestructiveOpBlockedException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame('drop_index', $thrown->opKind);
    }

    #[Test]
    public function addForeignKeyAlwaysFailsOnSqliteRegardlessOfPolicy(): void
    {
        [, , $migrator] = self::harness();

        $op = new AddForeignKey('orders', new ForeignKeySpec(
            referencedTable: 'users',
            localColumns: ['user_id'],
            referencedColumns: ['id'],
            name: 'fk_orders_user',
        ));

        $thrown = null;
        try {
            $migrator->run(
                [],
                [self::v2('waaseyaa/test:v2:fk', new CompositeDiff([$op]))],
                new PlanPolicy(allowDestructive: true),
            );
        } catch (ForeignKeyUnsupportedException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(
            SqliteDiagnosticCode::ForeignKeyUnsupportedSqliteV1->value,
            $thrown->diagnosticCode,
        );
    }

    /**
     * @return array{0: \Doctrine\DBAL\Connection, 1: MigrationRepository, 2: Migrator}
     */
    private static function harness(): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repo = new MigrationRepository($connection);
        $repo->createTable();
        $migrator = new Migrator(
            $connection,
            $repo,
            new V2PlanExecutor($connection, SqliteCompiler::forVersion('3.40.0')),
        );
        return [$connection, $repo, $migrator];
    }

    private static function v2(string $id, CompositeDiff $root): MigrationInterfaceV2
    {
        return new class ($id, $root) implements MigrationInterfaceV2 {
            public function __construct(private readonly string $id, private readonly CompositeDiff $root) {}
            public function migrationId(): string
            {
                return $this->id;
            }
            public function package(): string
            {
                return 'waaseyaa/test';
            }
            public function dependencies(): array
            {
                return [];
            }
            public function plan(): MigrationPlan
            {
                return new MigrationPlan(migrationId: $this->id, package: 'waaseyaa/test', dependencies: [], root: $this->root);
            }
        };
    }
}

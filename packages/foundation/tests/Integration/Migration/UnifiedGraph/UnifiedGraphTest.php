<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Integration\Migration\UnifiedGraph;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\Executor\V2PlanExecutor;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\Migrator;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Foundation\Migration\TableBuilder;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

/**
 * End-to-end exercise of the unified DAG: real SQLite connection, real
 * Migrator, mixed legacy + v2 migrations with cross-kind deps. Verifies
 * topological ordering, ledger writes, and that the resulting schema
 * matches the expected post-state.
 */
#[CoversNothing]
final class UnifiedGraphTest extends TestCase
{
    private \Doctrine\DBAL\Connection $connection;
    private MigrationRepository $repository;
    private Migrator $migrator;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->repository = new MigrationRepository($this->connection);
        $this->repository->createTable();

        $this->migrator = new Migrator(
            $this->connection,
            $this->repository,
            new V2PlanExecutor($this->connection, SqliteCompiler::forVersion('3.40.0')),
        );
    }

    #[Test]
    public function appliesMixedLegacyAndV2InDeterministicOrder(): void
    {
        // Setup:
        //   legacy waaseyaa/base:001_create_widgets — creates "widgets" table
        //   v2 waaseyaa/groups:v2:add-archived-at  — depends on the legacy node, adds column
        //   legacy waaseyaa/extras:002_add_index   — depends on v2 (cross-kind backward), adds index
        //   v2 waaseyaa/extras:v2:noop             — empty plan, ledger-only
        $legacyBase = new class extends Migration {
            public function up(SchemaBuilder $schema): void
            {
                $schema->create('widgets', function (TableBuilder $table) {
                    $table->id();
                });
            }
        };

        $legacyExtras = new class extends Migration {
            public array $after = ['waaseyaa/groups:v2:add-archived-at'];
            public function up(SchemaBuilder $schema): void
            {
                $schema->getConnection()->executeStatement('CREATE INDEX idx_widgets_archived_at ON widgets (archived_at)');
            }
        };

        $v2AddArchived = new class implements MigrationInterfaceV2 {
            public function migrationId(): string
            {
                return 'waaseyaa/groups:v2:add-archived-at';
            }
            public function package(): string
            {
                return 'waaseyaa/groups';
            }
            public function dependencies(): array
            {
                return ['waaseyaa/base:001_create_widgets'];
            }
            public function plan(): MigrationPlan
            {
                return new MigrationPlan(
                    migrationId: $this->migrationId(),
                    package: $this->package(),
                    dependencies: $this->dependencies(),
                    root: new CompositeDiff([
                        new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
                    ]),
                );
            }
        };

        $v2Noop = new class implements MigrationInterfaceV2 {
            public function migrationId(): string
            {
                return 'waaseyaa/extras:v2:noop';
            }
            public function package(): string
            {
                return 'waaseyaa/extras';
            }
            public function dependencies(): array
            {
                return [];
            }
            public function plan(): MigrationPlan
            {
                return new MigrationPlan(
                    migrationId: $this->migrationId(),
                    package: $this->package(),
                    dependencies: $this->dependencies(),
                    root: CompositeDiff::empty(),
                );
            }
        };

        $result = $this->migrator->run(
            [
                'waaseyaa/base' => ['waaseyaa/base:001_create_widgets' => $legacyBase],
                'waaseyaa/extras' => ['waaseyaa/extras:002_add_index' => $legacyExtras],
            ],
            [$v2AddArchived, $v2Noop],
        );

        // 4 nodes applied in one batch.
        self::assertSame(4, $result->count);

        // Schema state: widgets has id + archived_at, index exists.
        $columns = array_column(
            $this->connection->executeQuery('PRAGMA table_info("widgets")')->fetchAllAssociative(),
            'name',
        );
        self::assertContains('id', $columns);
        self::assertContains('archived_at', $columns);

        $indexes = array_column(
            $this->connection->executeQuery('PRAGMA index_list("widgets")')->fetchAllAssociative(),
            'name',
        );
        self::assertContains('idx_widgets_archived_at', $indexes);

        // Ledger: 4 rows in single batch, ordered as expected by the DAG.
        $rows = $this->repository->getCompletedWithDetails();
        self::assertCount(4, $rows);
        foreach ($rows as $row) {
            self::assertSame(1, $row['batch']);
        }

        // Order assertion: the only edges are
        //   base → v2-add → legacy-add-index
        //   v2-noop has no deps
        // Tie-break (package ASC, id ASC) means v2-noop ties with base
        // initially; base's package "waaseyaa/base" < "waaseyaa/extras",
        // so base goes first.
        $appliedIds = $result->migrations;
        $basePos = array_search('waaseyaa/base:001_create_widgets', $appliedIds, true);
        $v2AddPos = array_search('waaseyaa/groups:v2:add-archived-at', $appliedIds, true);
        $legacyExtrasPos = array_search('waaseyaa/extras:002_add_index', $appliedIds, true);
        $v2NoopPos = array_search('waaseyaa/extras:v2:noop', $appliedIds, true);

        self::assertIsInt($basePos);
        self::assertIsInt($v2AddPos);
        self::assertIsInt($legacyExtrasPos);
        self::assertIsInt($v2NoopPos);
        self::assertLessThan($v2AddPos, $basePos, 'base must precede v2 add (dep edge)');
        self::assertLessThan($legacyExtrasPos, $v2AddPos, 'v2 add must precede legacy index (dep edge)');
    }

    #[Test]
    public function emptyV2PlanWritesLedgerRowAndExecutesNoSql(): void
    {
        $v2Empty = new class implements MigrationInterfaceV2 {
            public function migrationId(): string
            {
                return 'waaseyaa/test:v2:noop';
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
                return new MigrationPlan(
                    migrationId: $this->migrationId(),
                    package: $this->package(),
                    dependencies: $this->dependencies(),
                    root: CompositeDiff::empty(),
                );
            }
        };

        $result = $this->migrator->run([], [$v2Empty]);

        self::assertSame(1, $result->count);
        self::assertTrue($this->repository->hasRun('waaseyaa/test:v2:noop'));
    }

    #[Test]
    public function migratorWithoutExecutorRefusesV2Input(): void
    {
        $migratorNoV2 = new Migrator($this->connection, $this->repository);

        $v2 = new class implements MigrationInterfaceV2 {
            public function migrationId(): string
            {
                return 'waaseyaa/test:v2:foo';
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
                return new MigrationPlan(
                    migrationId: $this->migrationId(),
                    package: $this->package(),
                    dependencies: $this->dependencies(),
                    root: CompositeDiff::empty(),
                );
            }
        };

        $this->expectException(\LogicException::class);

        $migratorNoV2->run([], [$v2]);
    }
}

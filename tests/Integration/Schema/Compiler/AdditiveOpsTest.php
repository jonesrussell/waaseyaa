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
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

/**
 * Mission #529 / WP08 / T044: additive ops end-to-end through the full
 * pipeline (Diff → SqliteCompiler → Migrator → SQLite + ledger).
 *
 * Closes the additive surface of GitHub issue #518: every supported
 * additive op shape produces the expected SQLite schema state and
 * writes a ledger row with non-null checksum + diff_hash.
 */
#[CoversNothing]
final class AdditiveOpsTest extends TestCase
{
    #[Test]
    public function addColumnLandsTheColumnAndWritesLedgerRow(): void
    {
        [$connection, $repo, $migrator] = self::harness();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $migrator->run([], [self::v2('waaseyaa/test:v2:add-archived', new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]))]);

        $columns = self::columnNames($connection, 'widgets');
        self::assertContains('archived_at', $columns);

        $rows = $repo->allWithChecksums();
        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]->checksum);
        self::assertNotNull($rows[0]->diffHash);
    }

    #[Test]
    public function addIndexCreatesNamedIndex(): void
    {
        [$connection, , $migrator] = self::harness();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY, archived_at INTEGER)');

        $migrator->run([], [self::v2('waaseyaa/test:v2:idx', new CompositeDiff([
            new AddIndex('widgets', ['archived_at']),
        ]))]);

        $indexes = self::indexNames($connection, 'widgets');
        self::assertContains('idx_widgets_archived_at', $indexes);
    }

    #[Test]
    public function addCompositeUniqueIndex(): void
    {
        [$connection, , $migrator] = self::harness();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY, archived_at INTEGER, status TEXT)');

        $migrator->run([], [self::v2('waaseyaa/test:v2:uq', new CompositeDiff([
            new AddIndex('widgets', ['archived_at', 'status'], unique: true),
        ]))]);

        // Index list reports unique flag (1) for our composite index.
        $row = $connection->executeQuery('PRAGMA index_list("widgets")')->fetchAllAssociative();
        $unique = array_filter($row, static fn(array $r): bool => (int) $r['unique'] === 1);
        self::assertNotEmpty($unique, 'Expected a UNIQUE index on widgets.');
    }

    #[Test]
    public function multipleAdditiveOpsApplyInOrder(): void
    {
        [$connection, , $migrator] = self::harness();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $migrator->run([], [self::v2('waaseyaa/test:v2:multi', new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
            new AddColumn('widgets', 'status', new ColumnSpec(type: 'varchar', nullable: false, default: 'open', length: 32)),
            new AddIndex('widgets', ['archived_at']),
        ]))]);

        $columns = self::columnNames($connection, 'widgets');
        self::assertContains('archived_at', $columns);
        self::assertContains('status', $columns);
        self::assertContains('idx_widgets_archived_at', self::indexNames($connection, 'widgets'));
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

    /** @return list<string> */
    private static function columnNames(\Doctrine\DBAL\Connection $c, string $table): array
    {
        return array_column(
            $c->executeQuery('PRAGMA table_info(' . $table . ')')->fetchAllAssociative(),
            'name',
        );
    }

    /** @return list<string> */
    private static function indexNames(\Doctrine\DBAL\Connection $c, string $table): array
    {
        return array_column(
            $c->executeQuery('PRAGMA index_list("' . $table . '")')->fetchAllAssociative(),
            'name',
        );
    }
}

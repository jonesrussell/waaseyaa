<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Schema\Migration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\ChecksumMismatchException;
use Waaseyaa\Foundation\Migration\Executor\V2PlanExecutor;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\Migrator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

/**
 * Mission #529 / WP08 / T049: idempotency + WP09 checksum guard.
 *
 * - Re-applying the same v2 plan is a silent no-op: zero SQL, no new
 *   ledger row, count returned by Migrator::run() is 0.
 * - Re-applying with the same migration_id but a different
 *   CompositeDiff fails in production with `CHECKSUM_MISMATCH` (per
 *   WP09's replay guard).
 */
#[CoversNothing]
final class IdempotencyTest extends TestCase
{
    #[Test]
    public function reapplyingSamePlanIsNoOp(): void
    {
        [$connection, $repo, $migrator] = self::harness();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $first = $migrator->run([], [self::v2('waaseyaa/test:v2:archived', new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]))]);
        self::assertSame(1, $first->count);

        // Snapshot the schema and ledger so we can compare after re-run.
        $columnsBefore = self::columnNames($connection, 'widgets');
        $rowsBefore = $repo->allWithChecksums();

        $second = $migrator->run([], [self::v2('waaseyaa/test:v2:archived', new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]))]);

        self::assertSame(0, $second->count);
        self::assertSame($columnsBefore, self::columnNames($connection, 'widgets'));
        self::assertCount(count($rowsBefore), $repo->allWithChecksums());
    }

    #[Test]
    public function reapplyingWithDriftedChecksumFailsInProduction(): void
    {
        [$connection, , $migrator] = self::harness(isProduction: true);
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        // Original.
        $migrator->run([], [self::v2('waaseyaa/test:v2:archived', new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]))]);

        // Drifted source under same migration_id.
        $thrown = null;
        try {
            $migrator->run([], [self::v2('waaseyaa/test:v2:archived', new CompositeDiff([
                new AddColumn('widgets', 'deleted_at', new ColumnSpec(type: 'int', nullable: true)),
            ]))]);
        } catch (ChecksumMismatchException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame('CHECKSUM_MISMATCH', $thrown->diagnosticCode());
        self::assertSame('waaseyaa/test:v2:archived', $thrown->migration);
    }

    /**
     * @return array{0: \Doctrine\DBAL\Connection, 1: MigrationRepository, 2: Migrator}
     */
    private static function harness(bool $isProduction = false): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repo = new MigrationRepository($connection);
        $repo->createTable();
        $migrator = new Migrator(
            $connection,
            $repo,
            new V2PlanExecutor($connection, SqliteCompiler::forVersion('3.40.0')),
            $isProduction,
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
}

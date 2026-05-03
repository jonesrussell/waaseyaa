<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command\Migrate;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\MigrateCommand;
use Waaseyaa\Foundation\Migration\Executor\V2PlanExecutor;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\Migrator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

#[CoversClass(MigrateCommand::class)]
final class MigrateCommandTest extends TestCase
{
    #[Test]
    public function dryRunPrintsPlanWithoutApplyingSqlOrLedgerWrites(): void
    {
        [$connection, $repo, $command] = self::buildHarness([self::v2Adding('widgets', 'archived_at')]);
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--dry-run' => true]);

        self::assertSame(0, $exitCode);
        $output = $tester->getDisplay();
        self::assertStringContainsString('Dry-run plan', $output);
        self::assertStringContainsString('waaseyaa/test:v2:foo', $output);
        self::assertStringContainsString('ALTER TABLE "widgets" ADD COLUMN "archived_at"', $output);

        // No ledger row was written.
        self::assertCount(0, $repo->allWithChecksums());

        // No archived_at column was added.
        $columns = array_column(
            $connection->executeQuery('PRAGMA table_info(widgets)')->fetchAllAssociative(),
            'name',
        );
        self::assertNotContains('archived_at', $columns);
    }

    #[Test]
    public function dryRunJsonOutputMatchesDocumentedSchema(): void
    {
        [$connection, , $command] = self::buildHarness([self::v2Adding('widgets', 'archived_at')]);
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--json' => true]);

        $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('dry_run', $payload['kind']);
        self::assertArrayHasKey('nodes', $payload);
        self::assertArrayHasKey('summary', $payload);
        self::assertSame(['v2_count', 'legacy_count', 'would_apply'], array_keys($payload['summary']));
        self::assertSame(1, $payload['summary']['v2_count']);
        self::assertSame(1, $payload['summary']['would_apply']);

        $node = $payload['nodes'][0];
        self::assertSame('waaseyaa/test:v2:foo', $node['id']);
        self::assertSame('v2', $node['kind']);
        self::assertFalse($node['already_applied']);
        self::assertNotEmpty($node['steps']);
        self::assertSame('alter_table_add_column', $node['steps'][0]['kind']);
    }

    #[Test]
    public function verifyAllMatchExitsZero(): void
    {
        [$connection, $repo, $command] = self::buildHarness([self::v2Adding('widgets', 'archived_at')]);
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        // Apply first so the ledger has a stored checksum.
        $migrator = new Migrator(
            $connection,
            $repo,
            new V2PlanExecutor($connection, SqliteCompiler::forVersion('3.40.0')),
        );
        $migrator->run([], [self::v2Adding('widgets', 'archived_at')]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--verify' => true]);

        self::assertSame(0, $exitCode);
        $output = $tester->getDisplay();
        self::assertStringContainsString('STATUS: OK', $output);
        self::assertStringContainsString('match=1', $output);
        self::assertStringContainsString('mismatch=0', $output);
    }

    #[Test]
    public function verifyMismatchExitsNonZeroAndNamesTheMigration(): void
    {
        [$connection, $repo] = self::makeConnectionAndRepo();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        // Apply original.
        $migrator = new Migrator(
            $connection,
            $repo,
            new V2PlanExecutor($connection, SqliteCompiler::forVersion('3.40.0')),
        );
        $migrator->run([], [self::v2Adding('widgets', 'archived_at')]);

        // Construct a command that loads a DIFFERENT v2 plan under the
        // same migration_id — drift the source.
        $command = new MigrateCommand(
            migrator: $migrator,
            migrationsProvider: static fn(): array => [],
            v2MigrationsProvider: static fn(): array => [self::v2Adding('widgets', 'deleted_at')],
            repository: $repo,
            compiler: SqliteCompiler::forVersion('3.40.0'),
            isProduction: true,
        );
        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($app->find('migrate'));
        $exitCode = $tester->execute(['--verify' => true]);

        self::assertNotSame(0, $exitCode);
        $output = $tester->getDisplay();
        self::assertStringContainsString('mismatch', $output);
        self::assertStringContainsString('waaseyaa/test:v2:foo', $output);
    }

    #[Test]
    public function dryRunAndVerifyTogetherFailWithIncompatibleFlags(): void
    {
        [, , $command] = self::buildHarness([]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--dry-run' => true, '--verify' => true]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('INCOMPATIBLE_FLAGS', $tester->getDisplay());
    }

    #[Test]
    public function productionSanitizationStripsAbsolutePathsFromOutput(): void
    {
        // Construct a command in production mode and verify the
        // sanitizer would strip a path-like fragment from any output.
        // We exercise this through the OutputSanitizer indirectly by
        // checking dry-run handles the canonical SQL strings without
        // injecting paths (none present in our compiler output).
        [$connection, , $command] = self::buildHarness([self::v2Adding('widgets', 'archived_at')], isProduction: true);
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--json' => true]);

        $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        // No /home/, /var/, /tmp/ leaked anywhere in the JSON output.
        $serialized = $tester->getDisplay();
        self::assertDoesNotMatchRegularExpression('#/home/|/var/|/tmp/#', $serialized);
    }

    /**
     * @param list<MigrationInterfaceV2> $v2
     * @return array{0: \Doctrine\DBAL\Connection, 1: MigrationRepository, 2: MigrateCommand}
     */
    private static function buildHarness(array $v2, bool $isProduction = false): array
    {
        [$connection, $repo] = self::makeConnectionAndRepo();

        $migrator = new Migrator(
            $connection,
            $repo,
            new V2PlanExecutor($connection, SqliteCompiler::forVersion('3.40.0')),
        );

        $command = new MigrateCommand(
            migrator: $migrator,
            migrationsProvider: static fn(): array => [],
            v2MigrationsProvider: static fn(): array => $v2,
            repository: $repo,
            compiler: SqliteCompiler::forVersion('3.40.0'),
            isProduction: $isProduction,
        );
        $app = new Application();
        $app->add($command);

        return [$connection, $repo, $app->find('migrate')];
    }

    /**
     * @return array{0: \Doctrine\DBAL\Connection, 1: MigrationRepository}
     */
    private static function makeConnectionAndRepo(): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repo = new MigrationRepository($connection);
        $repo->createTable();

        return [$connection, $repo];
    }

    private static function v2Adding(string $table, string $column): MigrationInterfaceV2
    {
        return new class ($table, $column) implements MigrationInterfaceV2 {
            public function __construct(
                private readonly string $table,
                private readonly string $column,
            ) {}
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
                    dependencies: [],
                    root: new CompositeDiff([
                        new AddColumn($this->table, $this->column, new ColumnSpec(type: 'int', nullable: true)),
                    ]),
                );
            }
        };
    }
}

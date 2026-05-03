<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Migration\Ledger;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\LogLevel;
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

#[CoversClass(Migrator::class)]
#[CoversClass(ChecksumMismatchException::class)]
final class ChecksumReplayGuardTest extends TestCase
{
    #[Test]
    public function reapplyWithSameChecksumIsSilentNoOp(): void
    {
        [$connection, $repo] = self::createConnectionAndRepo();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $migrator = self::migrator($connection, $repo, isProduction: true);

        $v2 = self::v2('waaseyaa/test:v2:foo', new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]));

        // First apply succeeds.
        $migrator->run([], [$v2]);

        // Second apply with the same plan: no-op, no exception.
        $result = $migrator->run([], [$v2]);
        self::assertSame(0, $result->count);
    }

    #[Test]
    public function productionThrowsOnChecksumMismatch(): void
    {
        [$connection, $repo] = self::createConnectionAndRepo();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $migrator = self::migrator($connection, $repo, isProduction: true);

        $original = self::v2('waaseyaa/test:v2:foo', new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]));
        $migrator->run([], [$original]);

        // Same migration_id, different structural intent.
        $drifted = self::v2('waaseyaa/test:v2:foo', new CompositeDiff([
            new AddColumn('widgets', 'deleted_at', new ColumnSpec(type: 'int', nullable: true)),
        ]));

        $thrown = null;
        try {
            $migrator->run([], [$drifted]);
        } catch (ChecksumMismatchException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame('CHECKSUM_MISMATCH', $thrown->diagnosticCode());
        self::assertSame('waaseyaa/test:v2:foo', $thrown->migration);
        self::assertNotSame($thrown->stored, $thrown->computed);
    }

    #[Test]
    public function developmentLogsWarningInsteadOfThrowing(): void
    {
        [$connection, $repo] = self::createConnectionAndRepo();
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY)');

        $logger = new class implements LoggerInterface {
            /** @var list<array{level: LogLevel, message: string}> */
            public array $records = [];
            public function debug(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::DEBUG, $message);
            }
            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::INFO, $message);
            }
            public function notice(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::NOTICE, $message);
            }
            public function warning(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::WARNING, $message);
            }
            public function error(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::ERROR, $message);
            }
            public function critical(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::CRITICAL, $message);
            }
            public function alert(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::ALERT, $message);
            }
            public function emergency(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::EMERGENCY, $message);
            }
            public function log(LogLevel $level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message];
            }
        };

        $migrator = self::migrator($connection, $repo, isProduction: false, logger: $logger);

        $original = self::v2('waaseyaa/test:v2:foo', new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]));
        $migrator->run([], [$original]);

        $drifted = self::v2('waaseyaa/test:v2:foo', new CompositeDiff([
            new AddColumn('widgets', 'deleted_at', new ColumnSpec(type: 'int', nullable: true)),
        ]));

        // Should NOT throw in dev mode.
        $result = $migrator->run([], [$drifted]);
        self::assertSame(0, $result->count);

        // Warning was logged.
        self::assertNotEmpty($logger->records);
        $warnings = array_filter($logger->records, static fn(array $r): bool => $r['level'] === LogLevel::WARNING);
        self::assertNotEmpty($warnings);
        $messages = array_column($warnings, 'message');
        self::assertStringContainsString('waaseyaa/test:v2:foo', implode("\n", $messages));
    }

    /**
     * @return array{0: \Doctrine\DBAL\Connection, 1: MigrationRepository}
     */
    private static function createConnectionAndRepo(): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repo = new MigrationRepository($connection);
        $repo->createTable();

        return [$connection, $repo];
    }

    private static function migrator(
        \Doctrine\DBAL\Connection $connection,
        MigrationRepository $repo,
        bool $isProduction,
        ?LoggerInterface $logger = null,
    ): Migrator {
        return new Migrator(
            $connection,
            $repo,
            new V2PlanExecutor($connection, SqliteCompiler::forVersion('3.40.0')),
            $isProduction,
            $logger,
        );
    }

    private static function v2(string $id, CompositeDiff $root): MigrationInterfaceV2
    {
        return new class ($id, $root) implements MigrationInterfaceV2 {
            public function __construct(
                private readonly string $id,
                private readonly CompositeDiff $root,
            ) {}
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
                return new MigrationPlan(
                    migrationId: $this->id,
                    package: 'waaseyaa/test',
                    dependencies: [],
                    root: $this->root,
                );
            }
        };
    }
}

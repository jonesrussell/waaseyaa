<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command\Import;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\Command\Import\ImportStatusCommand;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Testing\CliTester;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Migration\Discovery\HasMigrationsInterface;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\PluginFixtures\InMemoryDestination;
use Waaseyaa\Migration\PluginFixtures\InMemorySource;
use Waaseyaa\Migration\Schema\MigrationIdMapSchema;
use Waaseyaa\Migration\SourceId;

#[CoversClass(ImportStatusCommand::class)]
final class ImportStatusCommandTest extends TestCase
{
    #[Test]
    public function lists_three_states_for_three_migrations(): void
    {
        $idMap = $this->freshIdMap($database);

        // mig_complete: source count 2, id-map rows 2 → complete.
        $migComplete = $this->definition('mig_complete', ['a', 'b']);
        $idMap->upsert('mig_complete', new SourceId('in_memory', ['id' => 'a']), 'node', 'u1', 'h', 'r', new \DateTimeImmutable('2026-05-13T10:00:00Z'));
        $idMap->upsert('mig_complete', new SourceId('in_memory', ['id' => 'b']), 'node', 'u2', 'h', 'r', new \DateTimeImmutable('2026-05-13T10:01:00Z'));

        // mig_partial: source count 5, id-map rows 2 → partial.
        $migPartial = $this->definition('mig_partial', ['a', 'b', 'c', 'd', 'e']);
        $idMap->upsert('mig_partial', new SourceId('in_memory', ['id' => 'a']), 'node', 'u3', 'h', 'r', new \DateTimeImmutable('2026-05-13T09:00:00Z'));
        $idMap->upsert('mig_partial', new SourceId('in_memory', ['id' => 'b']), 'node', 'u4', 'h', 'r', new \DateTimeImmutable('2026-05-13T09:05:00Z'));

        // mig_pending: no id-map rows.
        $migPending = $this->definition('mig_pending', ['a']);

        $tester = $this->makeTester([$migComplete, $migPartial, $migPending], $idMap);
        $tester->execute([]);

        self::assertSame(0, $tester->getExitCode());
        $stdout = $tester->getStdout();
        self::assertStringContainsString('mig_complete', $stdout);
        self::assertStringContainsString('complete', $stdout);
        self::assertStringContainsString('mig_partial', $stdout);
        self::assertStringContainsString('partial', $stdout);
        self::assertStringContainsString('mig_pending', $stdout);
        self::assertStringContainsString('pending', $stdout);
        // Header.
        self::assertStringContainsString('ID', $stdout);
        self::assertStringContainsString('STATE', $stdout);
        self::assertStringContainsString('LAST RUN', $stdout);
        // mig_complete's last run timestamp surfaces in the row.
        self::assertStringContainsString('2026-05-13T10:01:00Z', $stdout);
    }

    #[Test]
    public function filter_argument_narrows_output(): void
    {
        $idMap = $this->freshIdMap($database);
        $migA = $this->definition('mig_a', ['x']);
        $migB = $this->definition('mig_b', ['y']);

        $tester = $this->makeTester([$migA, $migB], $idMap);
        $tester->execute(['mig_b']);

        self::assertSame(0, $tester->getExitCode());
        $stdout = $tester->getStdout();
        self::assertStringContainsString('mig_b', $stdout);
        // Each id appears once in the row + once in nowhere else (header has 'ID' only).
        self::assertSame(1, \substr_count($stdout, 'mig_b'));
        self::assertSame(0, \substr_count($stdout, 'mig_a'));
    }

    #[Test]
    public function unknown_filter_returns_usage_error(): void
    {
        $idMap = $this->freshIdMap($database);
        $tester = $this->makeTester([$this->definition('mig_a', [])], $idMap);
        $tester->execute(['nope']);

        self::assertSame(2, $tester->getExitCode());
        self::assertStringContainsString('unknown migration "nope"', $tester->getStderr());
    }

    /**
     * @param list<string> $ids
     */
    private function definition(string $migrationId, array $ids): MigrationDefinition
    {
        $records = \array_map(
            static fn(string $id): SourceRecord => new SourceRecord('in_memory', ['id' => $id, 'value' => 'v']),
            $ids,
        );
        return new MigrationDefinition(
            id: $migrationId,
            source: new InMemorySource(id: 'in_memory', records: $records),
            process: ['value' => 'value'],
            destination: new InMemoryDestination(),
        );
    }

    private function freshIdMap(?DBALDatabase &$database = null): MigrationIdMap
    {
        $database = DBALDatabase::createSqlite();
        $database->getConnection()->executeStatement(MigrationIdMapSchema::createTableSql());
        foreach (MigrationIdMapSchema::createIndexSqls() as $sql) {
            $database->getConnection()->executeStatement($sql);
        }
        return new MigrationIdMap($database);
    }

    /**
     * @param list<MigrationDefinition> $definitions
     */
    private function makeTester(array $definitions, MigrationIdMap $idMap): CliTester
    {
        $provider = new class($definitions) implements HasMigrationsInterface {
            /** @param list<MigrationDefinition> $defs */
            public function __construct(private readonly array $defs) {}
            public function migrations(): iterable
            {
                yield from $this->defs;
            }
        };
        $registry = new MigrationRegistry([$provider]);
        $registry->boot();

        $command = new ImportStatusCommand($registry, $idMap);
        $definitionCli = new CommandDefinition(
            name: 'import:status',
            description: 'Report per-migration import state (FR-034).',
            arguments: [
                new ArgumentDefinition(name: 'migration_id', mode: ArgumentMode::Optional),
            ],
            handler: [ImportStatusCommand::class, 'execute'],
        );
        $container = new class($command) implements ContainerInterface {
            public function __construct(private readonly ImportStatusCommand $command) {}
            public function get(string $id): mixed
            {
                if ($id === ImportStatusCommand::class) {
                    return $this->command;
                }
                throw new \RuntimeException("Not bound: {$id}");
            }
            public function has(string $id): bool
            {
                return $id === ImportStatusCommand::class;
            }
        };

        return CliTester::for($definitionCli, $container);
    }
}

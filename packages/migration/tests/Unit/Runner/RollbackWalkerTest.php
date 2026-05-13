<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Runner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Migration\Discovery\HasMigrationsInterface;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\PluginFixtures\InMemorySource;
use Waaseyaa\Migration\Runner\RollbackError;
use Waaseyaa\Migration\Runner\RollbackReport;
use Waaseyaa\Migration\Runner\RollbackWalker;
use Waaseyaa\Migration\Schema\MigrationIdMapSchema;
use Waaseyaa\Migration\SourceId;

/**
 * Unit coverage for {@see RollbackWalker} against a real
 * {@see MigrationIdMap} (in-memory SQLite) and stub destination plugins.
 *
 * Real-id-map + stub-destination is the right test boundary because the
 * walker's job is to orchestrate the walk; the destination's write/delete
 * semantics are covered by `EntityDestinationTest`.
 */
#[CoversClass(RollbackWalker::class)]
#[CoversClass(RollbackReport::class)]
#[CoversClass(RollbackError::class)]
final class RollbackWalkerTest extends TestCase
{
    private const string MIGRATION_ID = 'unit_walker';
    private const string ENTITY_TYPE_ID = 'fake_entity';

    private DBALDatabase $db;
    private MigrationIdMap $idMap;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite();
        $this->db->getConnection()->executeStatement(MigrationIdMapSchema::createTableSql());
        foreach (MigrationIdMapSchema::createIndexSqls() as $sql) {
            $this->db->getConnection()->executeStatement($sql);
        }
        $this->idMap = new MigrationIdMap($this->db);
    }

    #[Test]
    public function empty_id_map_yields_a_clean_report(): void
    {
        $destination = new RecordingDestination();
        $walker = $this->makeWalker($destination);

        $report = $walker->rollback(self::MIGRATION_ID);

        self::assertSame(0, $report->visited);
        self::assertSame(0, $report->rolledBack);
        self::assertSame(0, $report->failed);
        self::assertSame([], $report->errors);
        self::assertSame(self::MIGRATION_ID, $report->migrationId);
        self::assertSame(
            self::MIGRATION_ID . ': rollback complete (0/0, 0 failed)',
            $report->summaryLine(),
        );
        self::assertSame([], $destination->rolledBack);
    }

    #[Test]
    public function happy_path_calls_destination_rollback_per_row_in_reverse_creation_order(): void
    {
        $written = $this->seedIdMap(5);

        $destination = new RecordingDestination();
        $walker = $this->makeWalker($destination);

        $report = $walker->rollback(self::MIGRATION_ID);

        self::assertSame(5, $report->visited);
        self::assertSame(5, $report->rolledBack);
        self::assertSame(0, $report->failed);
        // Reverse-creation: last-written is rolled-back first (FR-043).
        self::assertSame(
            \array_reverse(\array_map(static fn(WriteResult $w): string => $w->destinationUuid, $written)),
            \array_map(static fn(WriteResult $w): string => $w->destinationUuid, $destination->rolledBack),
        );
        // Every id-map row was deleted on successful rollback.
        self::assertSame(0, $this->idMap->countForMigration(self::MIGRATION_ID));
    }

    #[Test]
    public function per_record_failure_does_not_halt_the_walk(): void
    {
        $this->seedIdMap(4);

        // Second iteration raises; the walker must continue.
        $destination = new RecordingDestination();
        $destination->failAtCall = 2;
        $walker = $this->makeWalker($destination);

        $report = $walker->rollback(self::MIGRATION_ID);

        self::assertSame(4, $report->visited);
        self::assertSame(3, $report->rolledBack);
        self::assertSame(1, $report->failed);
        self::assertCount(1, $report->errors);
        $error = $report->errors[0];
        // The walker maps \RuntimeException to `runtime_error`.
        self::assertSame('runtime_error', $error->code);
        self::assertSame(self::ENTITY_TYPE_ID, $error->destinationEntityType);

        // Failed row's id-map entry stays on disk so an operator can retry.
        self::assertSame(1, $this->idMap->countForMigration(self::MIGRATION_ID));
    }

    #[Test]
    public function destination_write_exception_reason_surfaces_as_code(): void
    {
        $this->seedIdMap(2);

        $destination = new RecordingDestination();
        $destination->throwReasonOnCall = [1 => 'entity_delete_denied'];
        $walker = $this->makeWalker($destination);

        $report = $walker->rollback(self::MIGRATION_ID);

        self::assertSame(2, $report->visited);
        self::assertSame(1, $report->rolledBack);
        self::assertSame(1, $report->failed);
        self::assertSame('entity_delete_denied', $report->errors[0]->code);
    }

    #[Test]
    public function unknown_migration_raises_out_of_bounds_from_registry(): void
    {
        $destination = new RecordingDestination();
        $walker = $this->makeWalker($destination);

        $this->expectException(\OutOfBoundsException::class);
        $walker->rollback('no_such_migration');
    }

    #[Test]
    public function empty_migration_id_raises_invalid_argument(): void
    {
        $destination = new RecordingDestination();
        $walker = $this->makeWalker($destination);

        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line — verifying runtime guard. */
        $walker->rollback('');
    }

    /**
     * Seed `$count` id-map rows with distinct `last_imported_at` timestamps
     * (one second apart) so reverse-creation order is deterministic.
     *
     * @return list<WriteResult>
     */
    private function seedIdMap(int $count): array
    {
        $written = [];
        $base = new \DateTimeImmutable('2026-01-01T00:00:00Z');
        for ($i = 0; $i < $count; $i++) {
            $sourceId = new SourceId(sourceType: 'fake', keys: ['n' => $i]);
            $result = $this->idMap->upsert(
                migrationId: self::MIGRATION_ID,
                sourceId: $sourceId,
                destinationEntityType: self::ENTITY_TYPE_ID,
                destinationUuid: \sprintf('00000000-0000-7000-8000-%012d', $i),
                sourceRecordHash: \str_repeat((string) $i, 64),
                runId: '00000000-0000-7000-8000-000000000001',
                now: $base->modify(\sprintf('+%d seconds', $i)),
            );
            $written[] = $result;
        }
        return $written;
    }

    /**
     * Regression for issue #1448 — system clock skew during a rollback walk
     * MUST NOT raise out of `RollbackReport::__construct()`. The walker
     * clamps `$finishedAt` to `>= $startedAt`, so even if the injected
     * clock returns a finish stamp earlier than the start the report
     * still satisfies its monotonic invariant.
     */
    #[Test]
    public function clock_regression_during_walk_does_not_break_report_construction(): void
    {
        $this->seedIdMap(2);

        $destination = new RecordingDestination();

        // Clock returns "now" for the first call (startedAt) and "now - 5s"
        // for the second call (finishedAt). Without the clamp in
        // RollbackWalker::rollback(), this would trip
        // RollbackReport's `finishedAt >= startedAt` invariant.
        $start = new \DateTimeImmutable('2026-05-13T12:00:00+00:00');
        $skewed = new \DateTimeImmutable('2026-05-13T11:59:55+00:00');
        $sequence = [$start, $skewed];
        $clock = static function () use (&$sequence): \DateTimeImmutable {
            $next = \array_shift($sequence);
            \assert($next instanceof \DateTimeImmutable);
            return $next;
        };

        $walker = $this->makeWalker($destination, $clock);

        $report = $walker->rollback(self::MIGRATION_ID);

        self::assertSame(2, $report->visited);
        self::assertSame(2, $report->rolledBack);
        self::assertSame(0, $report->failed);
        self::assertTrue(
            $report->finishedAt >= $report->startedAt,
            'RollbackReport must satisfy the monotonic timestamp invariant even under clock skew.',
        );
        self::assertSame($start, $report->startedAt);
        // The walker advances finishedAt by exactly 1µs over the start stamp
        // when the clock regresses.
        self::assertSame(
            $start->modify('+1 microsecond')->format('Y-m-d\TH:i:s.uP'),
            $report->finishedAt->format('Y-m-d\TH:i:s.uP'),
        );
    }

    private function makeWalker(DestinationPluginInterface $destination, ?\Closure $clock = null): RollbackWalker
    {
        $definition = new MigrationDefinition(
            id: self::MIGRATION_ID,
            source: new InMemorySource(id: 'in_memory', records: [new SourceRecord('fake', ['n' => 0])]),
            process: ['value' => 'value'],
            destination: $destination,
        );
        $provider = new class([$definition]) implements HasMigrationsInterface {
            /** @param list<MigrationDefinition> $defs */
            public function __construct(private readonly array $defs) {}
            public function migrations(): iterable
            {
                yield from $this->defs;
            }
        };
        $registry = new MigrationRegistry([$provider]);
        $registry->boot();

        return new RollbackWalker(
            registry: $registry,
            idMap: $this->idMap,
            clock: $clock,
        );
    }
}

/**
 * Test stub: records every `rollback()` call. Optionally raises on a
 * configured call index so per-record failure paths can be exercised.
 *
 * @internal
 */
final class RecordingDestination implements DestinationPluginInterface
{
    /** @var list<WriteResult> */
    public array $rolledBack = [];

    /** @var int|null 1-indexed call number at which `rollback()` raises a \RuntimeException. */
    public ?int $failAtCall = null;

    /** @var array<int, string> 1-indexed call number → ::reason() value for a DestinationWriteException. */
    public array $throwReasonOnCall = [];

    private int $calls = 0;

    public function id(): string
    {
        return 'recording';
    }

    public function stability(): string
    {
        return 'experimental';
    }

    public function write(DestinationRecord $record): WriteResult
    {
        throw new \LogicException('RecordingDestination::write() not used by RollbackWalkerTest.');
    }

    public function rollback(WriteResult $result): void
    {
        $this->calls++;

        if (isset($this->throwReasonOnCall[$this->calls])) {
            throw new ReasonedRuntimeException('denied', $this->throwReasonOnCall[$this->calls]);
        }

        if ($this->failAtCall === $this->calls) {
            throw new \RuntimeException('Boom (per-record failure).');
        }

        $this->rolledBack[] = $result;
    }

    public function lookup(SourceId $sourceId): ?WriteResult
    {
        return null;
    }
}

/**
 * Test helper exception carrying a `::reason()` method, so the walker's
 * `codeFor()` method-exists branch is exercised under unit test.
 *
 * @internal
 */
final class ReasonedRuntimeException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $reasonCode)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reasonCode;
    }
}

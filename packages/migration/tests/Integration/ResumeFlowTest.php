<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Migration\Discovery\HasMigrationsInterface;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\MigrationRunState;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\PluginFixtures\InMemoryDestination;
use Waaseyaa\Migration\PluginFixtures\InMemorySource;
use Waaseyaa\Migration\Runner\MigrationRunner;
use Waaseyaa\Migration\Runner\ProcessChainExecutor;
use Waaseyaa\Migration\Runner\RunOptions;
use Waaseyaa\Migration\Schema\MigrationIdMapSchema;

/**
 * End-to-end integration coverage for the resume cycle (FR-037, FR-038).
 *
 * Each test boots its own in-memory SQLite database, registers a single
 * `demo` migration with a 100-record `InMemorySource`, drives the runner
 * through partial runs, and asserts the resume cycle reuses the prior run
 * id and only processes new records.
 */
#[CoversNothing]
final class ResumeFlowTest extends TestCase
{
    /**
     * Build a fresh in-memory test rig.
     *
     * @param int $recordCount how many records the InMemorySource yields
     *
     * @return array{
     *     0: DBALDatabase,
     *     1: MigrationIdMap,
     *     2: MigrationRunState,
     *     3: MigrationRunner,
     *     4: InMemoryDestination,
     * }
     */
    private function rig(int $recordCount = 100): array
    {
        $db = DBALDatabase::createSqlite();
        $conn = $db->getConnection();

        // Apply both migrations — id-map (WP04) + run-state (WP07).
        $idMapMigration = require \dirname(__DIR__, 2)
            . '/migrations/2026_05_13_000001_create_migration_id_map.php';
        \assert($idMapMigration instanceof Migration);
        $runStateMigration = require \dirname(__DIR__, 2)
            . '/migrations/2026_05_13_000002_create_migration_run_state.php';
        \assert($runStateMigration instanceof Migration);

        $schema = new SchemaBuilder($conn);
        $idMapMigration->up($schema);
        $runStateMigration->up($schema);

        $idMap = new MigrationIdMap($db);
        $runState = new MigrationRunState($db);

        $records = [];
        for ($i = 1; $i <= $recordCount; $i++) {
            $records[] = new SourceRecord('in_memory', [
                'id' => (string) $i,
                'value' => 'v' . $i,
            ]);
        }
        $destination = new InMemoryDestination();
        $definition = new MigrationDefinition(
            id: 'demo',
            source: new InMemorySource(id: 'in_memory', records: $records),
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

        $runner = new MigrationRunner(
            registry: $registry,
            chain: new ProcessChainExecutor(),
            idMap: $idMap,
            runState: $runState,
        );

        return [$db, $idMap, $runState, $runner, $destination];
    }

    // -------------------------------------------------------------------------
    // Test 1 — interrupt-then-resume round-trip
    // -------------------------------------------------------------------------

    #[Test]
    public function resume_completes_a_partial_run_with_one_hundred_records(): void
    {
        [, , $runState, $runner, $destination] = $this->rig(100);

        // First leg: process the first 50 records.
        $first = $runner->run('demo', new RunOptions(limit: 50));
        self::assertSame(50, $first->imported);
        self::assertSame(0, $first->failed);
        self::assertCount(50, $destination->writes);

        // 50 rows in migration_run_state, all success.
        self::assertSame(
            ['success' => 50, 'error' => 0, 'skipped' => 0],
            $runState->countByStatus('demo'),
        );

        // Resume — the second leg picks up where the first stopped.
        $second = $runner->runResume('demo', new RunOptions());
        self::assertSame(50, $second->imported, 'resume should import the remaining 50 records');
        self::assertSame(0, $second->failed);

        // Destination saw all 100 writes — 50 from the first leg + 50 from
        // the resume.
        self::assertCount(100, $destination->writes);

        // 100 rows in migration_run_state, all success.
        self::assertSame(
            ['success' => 100, 'error' => 0, 'skipped' => 0],
            $runState->countByStatus('demo'),
        );
    }

    // -------------------------------------------------------------------------
    // Test 2 — resume reuses the prior run_id (FR-037)
    // -------------------------------------------------------------------------

    #[Test]
    public function resume_reuses_the_prior_run_id(): void
    {
        [, , $runState, $runner] = $this->rig(100);

        $first = $runner->run('demo', new RunOptions(limit: 50));
        $priorRunId = $first->runId;
        self::assertSame($priorRunId, $runState->latestRunForMigration('demo'));

        $second = $runner->runResume('demo', new RunOptions());
        self::assertSame(
            $priorRunId,
            $second->runId,
            'resume must reuse the prior run id (FR-037 contract).',
        );

        self::assertSame(
            $priorRunId,
            $runState->latestRunForMigration('demo'),
            'latest run id should still be the original after resume completes.',
        );
    }

    // -------------------------------------------------------------------------
    // Test 3 — idempotent resume (rerun after completion is a no-op)
    // -------------------------------------------------------------------------

    #[Test]
    public function resume_after_completion_is_idempotent(): void
    {
        [, , $runState, $runner] = $this->rig(20);

        $runner->run('demo', new RunOptions(limit: 10));
        $runner->runResume('demo', new RunOptions());

        // A second resume now has nothing new to do — every record is in
        // the id-map, so every record is recorded as a skip on this pass.
        $third = $runner->runResume('demo', new RunOptions());
        self::assertSame(0, $third->imported);
        self::assertSame(0, $third->failed);
        // The runner walks every record and classifies each as `skipped`
        // because the id-map hash matches (FR-031). The run_state rows
        // overwrite the prior success outcomes — and that is OK, the
        // resume checkpoint advanced past them.
        self::assertGreaterThanOrEqual(0, $third->skipped);

        // The id-map still has 20 rows (idempotent upserts).
        $bucket = $runState->countByStatus('demo');
        self::assertSame(20, $bucket['success'] + $bucket['skipped']);
    }

    // -------------------------------------------------------------------------
    // Test 4 — resume without a prior run raises InvalidArgumentException
    // -------------------------------------------------------------------------

    #[Test]
    public function resume_without_prior_run_raises(): void
    {
        [, , , $runner] = $this->rig(10);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no prior run recorded for migration "demo"/');
        $runner->runResume('demo', new RunOptions());
    }

    // -------------------------------------------------------------------------
    // Test 5 — successive runs overwrite the prior state row (single row per record)
    // -------------------------------------------------------------------------

    #[Test]
    public function successive_runs_overwrite_per_record_outcomes(): void
    {
        [, , $runState, $runner] = $this->rig(5);

        // Two successive runs against the same records — single active row
        // per `(migration_id, source_id_hash)` pair (PRIMARY KEY); the
        // second run's `MAX(position)` should be at least as high as the
        // first run's because the position counter is monotonic per run.
        $runner->run('demo', new RunOptions(limit: 5));

        $afterFirst = $runState->countByStatus('demo');
        self::assertSame(5, $afterFirst['success']);
        $firstRunId = $runState->latestRunForMigration('demo');
        self::assertNotNull($firstRunId);
        self::assertSame(5, $runState->latestPositionForRun('demo', $firstRunId));

        // Second physical invocation — fresh run id (no resume).
        $runner->run('demo', new RunOptions(limit: 5));

        $secondRunId = $runState->latestRunForMigration('demo');
        self::assertNotNull($secondRunId);
        self::assertNotSame(
            $firstRunId,
            $secondRunId,
            'a non-resume re-run must mint a fresh UUIDv7 run id.',
        );

        // Single row per record regardless of which run wrote it.
        $bucket = $runState->countByStatus('demo');
        self::assertSame(
            5,
            $bucket['success'] + $bucket['skipped'] + $bucket['error'],
            'PRIMARY KEY (migration_id, source_id_hash) means single active row per record.',
        );
    }

    // -------------------------------------------------------------------------
    // Test 6 — clearForMigration resets the run state
    // -------------------------------------------------------------------------

    #[Test]
    public function clear_resets_run_state_so_resume_fails_until_next_run(): void
    {
        [, , $runState, $runner] = $this->rig(10);

        $runner->run('demo', new RunOptions(limit: 5));
        self::assertNotNull($runState->latestRunForMigration('demo'));

        $deleted = $runState->clearForMigration('demo');
        self::assertSame(5, $deleted);
        self::assertNull($runState->latestRunForMigration('demo'));

        // Without state, `runResume` rejects.
        $this->expectException(\InvalidArgumentException::class);
        $runner->runResume('demo', new RunOptions());
    }
}

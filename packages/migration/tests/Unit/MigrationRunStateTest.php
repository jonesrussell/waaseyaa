<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Migration\MigrationRunState;
use Waaseyaa\Migration\Schema\MigrationRunStateSchema;

#[CoversClass(MigrationRunState::class)]
#[CoversClass(MigrationRunStateSchema::class)]
final class MigrationRunStateTest extends TestCase
{
    private DBALDatabase $db;
    private MigrationRunState $runState;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite();

        // Apply the schema via the package migration file so the test
        // exercises the same DDL that ships in production.
        $migrationFile = \dirname(__DIR__, 2)
            . '/migrations/2026_05_13_000002_create_migration_run_state.php';
        $migration = require $migrationFile;
        \assert($migration instanceof Migration);

        $schema = new SchemaBuilder($this->db->getConnection());
        $migration->up($schema);

        $this->runState = new MigrationRunState($this->db);
    }

    // -------------------------------------------------------------------------
    // Schema invariants
    // -------------------------------------------------------------------------

    #[Test]
    public function table_and_index_exist_after_migration(): void
    {
        $schema = new SchemaBuilder($this->db->getConnection());
        self::assertTrue($schema->hasTable('migration_run_state'));

        $indexes = $this->db->getConnection()->fetchAllAssociative(
            "SELECT name FROM sqlite_master WHERE type = 'index' "
            . "AND tbl_name = 'migration_run_state'",
        );
        $indexNames = \array_map(static fn(array $row): string => (string) $row['name'], $indexes);
        self::assertContains('migration_run_state__run', $indexNames);
    }

    #[Test]
    public function schema_columns_match_data_model(): void
    {
        $expected = [
            'migration_id', 'source_id_hash', 'run_id', 'item_status',
            'error_code', 'error_message', 'position', 'updated_at',
        ];
        $rows = $this->db->getConnection()->fetchAllAssociative(
            "PRAGMA table_info('migration_run_state')",
        );
        $actual = \array_map(static fn(array $r): string => (string) $r['name'], $rows);
        self::assertSame($expected, $actual);
    }

    #[Test]
    public function schema_up_down_up_is_reversible(): void
    {
        $migrationFile = \dirname(__DIR__, 2)
            . '/migrations/2026_05_13_000002_create_migration_run_state.php';
        $migration = require $migrationFile;
        \assert($migration instanceof Migration);

        $schema = new SchemaBuilder($this->db->getConnection());

        // table is up from setUp; bring it down then back up.
        $migration->down($schema);
        self::assertFalse($schema->hasTable('migration_run_state'));

        $migration->up($schema);
        self::assertTrue($schema->hasTable('migration_run_state'));
    }

    // -------------------------------------------------------------------------
    // recordSuccess / recordSkipped / recordError
    // -------------------------------------------------------------------------

    #[Test]
    public function record_success_inserts_a_success_row(): void
    {
        $this->runState->recordSuccess(
            migrationId: 'm1',
            sourceIdHash: 'hash-a',
            runId: 'run-1',
            position: 1,
            now: new \DateTimeImmutable('2026-05-13T10:00:00Z'),
        );

        $row = $this->runState->lookupItem('m1', 'hash-a');
        self::assertNotNull($row);
        self::assertSame('success', $row['item_status']);
        self::assertSame('run-1', $row['run_id']);
        self::assertSame(1, $row['position']);
        self::assertNull($row['error_code']);
        self::assertNull($row['error_message']);
        self::assertSame('2026-05-13T10:00:00Z', $row['updated_at']);
    }

    #[Test]
    public function record_skipped_inserts_a_skipped_row(): void
    {
        $this->runState->recordSkipped('m1', 'hash-a', 'run-1', 7);

        $row = $this->runState->lookupItem('m1', 'hash-a');
        self::assertNotNull($row);
        self::assertSame('skipped', $row['item_status']);
        self::assertSame(7, $row['position']);
    }

    #[Test]
    public function record_error_persists_code_and_message(): void
    {
        $this->runState->recordError(
            migrationId: 'm1',
            sourceIdHash: 'hash-a',
            runId: 'run-1',
            position: 3,
            errorCode: 'ENTITY_SAVE_FAILED',
            errorMessage: 'database is locked',
        );

        $row = $this->runState->lookupItem('m1', 'hash-a');
        self::assertNotNull($row);
        self::assertSame('error', $row['item_status']);
        self::assertSame('ENTITY_SAVE_FAILED', $row['error_code']);
        self::assertSame('database is locked', $row['error_message']);
    }

    #[Test]
    public function re_record_overwrites_existing_outcome(): void
    {
        $this->runState->recordError('m1', 'hash-a', 'run-1', 3, 'TEST_FAILURE', 'fail');
        $this->runState->recordSuccess('m1', 'hash-a', 'run-2', 4);

        $row = $this->runState->lookupItem('m1', 'hash-a');
        self::assertNotNull($row);
        self::assertSame('success', $row['item_status']);
        self::assertSame('run-2', $row['run_id']);
        self::assertSame(4, $row['position']);
        self::assertNull($row['error_code']);
        self::assertNull($row['error_message']);
    }

    #[Test]
    public function record_error_with_empty_code_rejects(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runState->recordError('m1', 'hash-a', 'run-1', 1, '', 'oops');
    }

    #[Test]
    public function record_methods_reject_empty_run_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runState->recordSuccess('m1', 'hash-a', '', 1);
    }

    #[Test]
    public function record_methods_reject_empty_source_id_hash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runState->recordSuccess('m1', '', 'run-1', 1);
    }

    #[Test]
    public function record_methods_reject_negative_position(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runState->recordSuccess('m1', 'hash-a', 'run-1', -1);
    }

    // -------------------------------------------------------------------------
    // latestPositionForRun
    // -------------------------------------------------------------------------

    #[Test]
    public function latest_position_returns_null_when_no_rows(): void
    {
        self::assertNull($this->runState->latestPositionForRun('m1', 'run-1'));
    }

    #[Test]
    public function latest_position_returns_max(): void
    {
        $this->runState->recordSuccess('m1', 'hash-a', 'run-1', 1);
        $this->runState->recordSuccess('m1', 'hash-b', 'run-1', 2);
        $this->runState->recordSuccess('m1', 'hash-c', 'run-1', 3);

        self::assertSame(3, $this->runState->latestPositionForRun('m1', 'run-1'));
    }

    #[Test]
    public function latest_position_scoped_to_run(): void
    {
        $this->runState->recordSuccess('m1', 'hash-a', 'run-1', 5);
        $this->runState->recordSuccess('m1', 'hash-b', 'run-2', 1);

        self::assertSame(5, $this->runState->latestPositionForRun('m1', 'run-1'));
        self::assertSame(1, $this->runState->latestPositionForRun('m1', 'run-2'));
    }

    // -------------------------------------------------------------------------
    // latestRunForMigration
    // -------------------------------------------------------------------------

    #[Test]
    public function latest_run_returns_null_when_no_rows(): void
    {
        self::assertNull($this->runState->latestRunForMigration('m1'));
    }

    #[Test]
    public function latest_run_returns_most_recently_updated(): void
    {
        $this->runState->recordSuccess(
            'm1', 'hash-a', 'run-old', 1,
            new \DateTimeImmutable('2026-05-13T10:00:00Z'),
        );
        $this->runState->recordSuccess(
            'm1', 'hash-b', 'run-new', 2,
            new \DateTimeImmutable('2026-05-13T12:00:00Z'),
        );

        self::assertSame('run-new', $this->runState->latestRunForMigration('m1'));
    }

    #[Test]
    public function latest_run_breaks_clock_ties_by_run_id_desc(): void
    {
        // Tied timestamps — secondary sort by run_id DESC. UUIDv7 prefixes
        // are monotonically increasing for a single producer, so the
        // alphabetically larger id is the "later" run.
        $tied = new \DateTimeImmutable('2026-05-13T10:00:00Z');
        $this->runState->recordSuccess('m1', 'hash-a', 'run-alpha', 1, $tied);
        $this->runState->recordSuccess('m1', 'hash-b', 'run-charlie', 2, $tied);
        $this->runState->recordSuccess('m1', 'hash-c', 'run-bravo', 3, $tied);

        self::assertSame('run-charlie', $this->runState->latestRunForMigration('m1'));
    }

    // -------------------------------------------------------------------------
    // countByStatus
    // -------------------------------------------------------------------------

    #[Test]
    public function count_by_status_returns_zero_buckets_when_no_rows(): void
    {
        self::assertSame(
            ['success' => 0, 'error' => 0, 'skipped' => 0],
            $this->runState->countByStatus('m1'),
        );
    }

    #[Test]
    public function count_by_status_aggregates_buckets(): void
    {
        $this->runState->recordSuccess('m1', 'h1', 'r', 1);
        $this->runState->recordSuccess('m1', 'h2', 'r', 2);
        $this->runState->recordSuccess('m1', 'h3', 'r', 3);
        $this->runState->recordSkipped('m1', 'h4', 'r', 4);
        $this->runState->recordSkipped('m1', 'h5', 'r', 5);
        $this->runState->recordError('m1', 'h6', 'r', 6, 'X', 'x');

        self::assertSame(
            ['success' => 3, 'error' => 1, 'skipped' => 2],
            $this->runState->countByStatus('m1'),
        );
    }

    #[Test]
    public function count_by_status_scoped_to_migration(): void
    {
        $this->runState->recordSuccess('m1', 'h1', 'r', 1);
        $this->runState->recordError('m2', 'h2', 'r', 1, 'X', 'x');

        self::assertSame(
            ['success' => 1, 'error' => 0, 'skipped' => 0],
            $this->runState->countByStatus('m1'),
        );
        self::assertSame(
            ['success' => 0, 'error' => 1, 'skipped' => 0],
            $this->runState->countByStatus('m2'),
        );
    }

    // -------------------------------------------------------------------------
    // lookupItem + clearForMigration
    // -------------------------------------------------------------------------

    #[Test]
    public function lookup_returns_null_when_not_found(): void
    {
        self::assertNull($this->runState->lookupItem('m1', 'h-missing'));
    }

    #[Test]
    public function clear_removes_all_rows_for_migration(): void
    {
        $this->runState->recordSuccess('m1', 'h1', 'r', 1);
        $this->runState->recordSuccess('m1', 'h2', 'r', 2);
        $this->runState->recordSuccess('m2', 'h3', 'r', 1);

        $deleted = $this->runState->clearForMigration('m1');
        self::assertSame(2, $deleted);

        self::assertNull($this->runState->lookupItem('m1', 'h1'));
        self::assertNull($this->runState->lookupItem('m1', 'h2'));
        // m2 untouched
        self::assertNotNull($this->runState->lookupItem('m2', 'h3'));
    }

    #[Test]
    public function lookup_rejects_empty_migration_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runState->lookupItem('', 'hash-a');
    }

    #[Test]
    public function count_by_status_rejects_empty_migration_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runState->countByStatus('');
    }
}

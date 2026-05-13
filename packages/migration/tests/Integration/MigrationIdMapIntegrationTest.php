<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\SourceId;

/**
 * Integration coverage for the id-map: schema portability, transactional
 * atomicity, cross-connection visibility, and tie-broken reverse-walk
 * ordering.
 */
#[CoversNothing]
final class MigrationIdMapIntegrationTest extends TestCase
{
    /**
     * @return array{0: DBALDatabase, 1: MigrationIdMap}
     */
    private function freshSqliteIdMap(?string $sqlitePath = null): array
    {
        $db = $sqlitePath === null
            ? DBALDatabase::createSqlite()
            : DBALDatabase::createSqlite($sqlitePath);

        $migrationFile = \dirname(__DIR__, 2) . '/migrations/2026_05_13_000001_create_migration_id_map.php';
        $migration = require $migrationFile;
        \assert($migration instanceof Migration);

        $schema = new SchemaBuilder($db->getConnection());
        $migration->up($schema);

        return [$db, new MigrationIdMap($db)];
    }

    #[Test]
    public function atomic_upsert_rolls_back_on_failure(): void
    {
        [$db, $idMap] = $this->freshSqliteIdMap();

        $sourceId = new SourceId('wp', ['id' => 1]);

        try {
            $idMap->transactional(function () use ($idMap, $sourceId): void {
                $idMap->upsert('m1', $sourceId, 'node', 'uuid-1', 'hash-1', 'run-1');
                throw new \RuntimeException('rollback');
            });
            self::fail('Expected RuntimeException to surface from transactional()');
        } catch (\RuntimeException $e) {
            self::assertSame('rollback', $e->getMessage());
        }

        self::assertSame(0, $idMap->countForMigration('m1'));
        // Double-check via a fresh count query on the same connection.
        $rows = $db->select('migration_id_map', 't')->countQuery()->execute();
        foreach ($rows as $row) {
            \assert(\is_array($row));
            self::assertSame(0, (int) $row['count']);
            return;
        }
        self::fail('count query returned no rows');
    }

    #[Test]
    public function rows_committed_on_connection_a_are_visible_on_connection_b(): void
    {
        $sqlitePath = \sys_get_temp_dir() . '/waaseyaa_idmap_' . \uniqid() . '.sqlite';

        try {
            [, $idMapA] = $this->freshSqliteIdMap($sqlitePath);

            $sourceId = new SourceId('wp', ['id' => 99]);
            $idMapA->upsert('m1', $sourceId, 'node', 'uuid-99', 'hash-99', 'run-99');

            // Open a fresh connection to the same file.
            $connB = DriverManager::getConnection([
                'driver' => 'pdo_sqlite',
                'path' => $sqlitePath,
            ]);
            $dbB = new DBALDatabase($connB);
            $idMapB = new MigrationIdMap($dbB);

            $looked = $idMapB->lookupDestination('m1', $sourceId);
            self::assertNotNull($looked);
            self::assertSame('uuid-99', $looked->destinationUuid);
        } finally {
            if (\is_file($sqlitePath)) {
                @\unlink($sqlitePath);
            }
        }
    }

    #[Test]
    public function schema_is_reversible_up_down_up(): void
    {
        [$db] = $this->freshSqliteIdMap();

        $migrationFile = \dirname(__DIR__, 2) . '/migrations/2026_05_13_000001_create_migration_id_map.php';
        $migration = require $migrationFile;
        \assert($migration instanceof Migration);

        $schema = new SchemaBuilder($db->getConnection());

        // setUp helper already ran up(); roll back and re-apply.
        $migration->down($schema);
        self::assertFalse((new SchemaBuilder($db->getConnection()))->hasTable('migration_id_map'));

        $migration->up($schema);
        self::assertTrue((new SchemaBuilder($db->getConnection()))->hasTable('migration_id_map'));
    }

    #[Test]
    public function reverse_walk_uses_run_id_as_deterministic_tiebreaker(): void
    {
        [, $idMap] = $this->freshSqliteIdMap();

        $tied = new \DateTimeImmutable('2026-05-13T10:00:00Z');
        $a = new SourceId('wp', ['id' => 1]);
        $b = new SourceId('wp', ['id' => 2]);
        $c = new SourceId('wp', ['id' => 3]);

        $idMap->upsert('m1', $a, 'node', 'u-a', 'h', 'run-alpha', $tied);
        $idMap->upsert('m1', $b, 'node', 'u-b', 'h', 'run-bravo', $tied);
        $idMap->upsert('m1', $c, 'node', 'u-c', 'h', 'run-charlie', $tied);

        $order = [];
        foreach ($idMap->walkReverseCreation('m1') as $result) {
            $order[] = $result->runId;
        }

        // Tied timestamps -> run_id DESC: charlie, bravo, alpha.
        self::assertSame(['run-charlie', 'run-bravo', 'run-alpha'], $order);
    }
}

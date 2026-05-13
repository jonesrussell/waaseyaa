<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\Schema\MigrationIdMapSchema;
use Waaseyaa\Migration\SourceId;

#[CoversClass(MigrationIdMap::class)]
final class MigrationIdMapMaxLastImportedAtTest extends TestCase
{
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
    public function returns_null_when_migration_has_no_rows(): void
    {
        self::assertNull($this->idMap->maxLastImportedAt('m1'));
    }

    #[Test]
    public function returns_most_recent_timestamp(): void
    {
        $this->idMap->upsert(
            migrationId: 'm1',
            sourceId: new SourceId('wp', ['id' => 1]),
            destinationEntityType: 'node',
            destinationUuid: 'u1',
            sourceRecordHash: 'h1',
            runId: 'r1',
            now: new \DateTimeImmutable('2026-05-13T10:00:00Z'),
        );
        $this->idMap->upsert(
            migrationId: 'm1',
            sourceId: new SourceId('wp', ['id' => 2]),
            destinationEntityType: 'node',
            destinationUuid: 'u2',
            sourceRecordHash: 'h2',
            runId: 'r2',
            now: new \DateTimeImmutable('2026-05-13T11:00:00Z'),
        );
        // An older row for a different migration must not influence m1's max.
        $this->idMap->upsert(
            migrationId: 'm2',
            sourceId: new SourceId('wp', ['id' => 3]),
            destinationEntityType: 'node',
            destinationUuid: 'u3',
            sourceRecordHash: 'h3',
            runId: 'r3',
            now: new \DateTimeImmutable('2026-05-13T12:00:00Z'),
        );

        self::assertSame('2026-05-13T11:00:00Z', $this->idMap->maxLastImportedAt('m1'));
        self::assertSame('2026-05-13T12:00:00Z', $this->idMap->maxLastImportedAt('m2'));
    }

    #[Test]
    public function rejects_empty_migration_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->idMap->maxLastImportedAt('');
    }
}

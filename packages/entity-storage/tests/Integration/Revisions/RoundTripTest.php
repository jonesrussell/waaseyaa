<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Integration\Revisions;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\EntityStorage\Event\AfterSaveEvent;
use Waaseyaa\EntityStorage\Event\BeforeSaveEvent;
use Waaseyaa\EntityStorage\RevisionableSqlBlobStorage;
use Waaseyaa\EntityStorage\RevisionableSqlColumnStorage;
use Waaseyaa\EntityStorage\RevisionPruner;
use Waaseyaa\EntityStorage\RevisionPruningPolicy;
use Waaseyaa\EntityStorage\RevisionPruningReport;
use Waaseyaa\EntityStorage\SaveContext;
use Waaseyaa\EntityStorage\Tests\Fixtures\RevisionableArticleEntity;

/**
 * WP08 integration tests: revision load / list / setCurrentRevision round-trips.
 *
 * Tests operate against in-memory SQLite tables created directly by each test method.
 * No EntityRepository or RevisionableStorageDriver involved — we exercise the
 * new RevisionableEntityStorageInterface implementations in isolation.
 *
 * ## Coverage
 *
 * T040 — RevisionableEntityStorageInterface contract (structural, via use in tests)
 * T041 — loadRevision() returns snapshot; isCurrentRevision() set correctly
 * T042 — listRevisions() yields in descending revision_created_at order
 * T043 — setCurrentRevision() updates primary table; dispatches Before/AfterSave
 * T044 — SaveContext::withoutNewRevision honours EntityStorageCoordinator delta
 * T045 — RevisionPruner is disabled / returns no-op report
 * T046 — All scenarios wired together in round-trip tests
 */
#[CoversNothing]
final class RoundTripTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Schema helpers
    // -------------------------------------------------------------------------

    private function makePrimaryTableSql(string $table, string $idCol): string
    {
        return <<<SQL
            CREATE TABLE IF NOT EXISTS "{$table}" (
                "{$idCol}" INTEGER PRIMARY KEY AUTOINCREMENT,
                "vid"    INTEGER,
                "uuid"   TEXT,
                "title"  TEXT,
                "_data"  TEXT DEFAULT '{}'
            )
            SQL;
    }

    private function makeRevisionTableBlobSql(string $table, string $idCol): string
    {
        return <<<SQL
            CREATE TABLE IF NOT EXISTS "{$table}__revision" (
                "vid"                  INTEGER PRIMARY KEY AUTOINCREMENT,
                "{$idCol}"             INTEGER NOT NULL,
                "revision_created_at"  TEXT,
                "revision_author"      INTEGER,
                "revision_log"         TEXT,
                "_data"                TEXT
            )
            SQL;
    }

    private function makeRevisionTableColumnSql(string $table, string $idCol): string
    {
        return <<<SQL
            CREATE TABLE IF NOT EXISTS "{$table}__revision" (
                "vid"                  INTEGER PRIMARY KEY AUTOINCREMENT,
                "{$idCol}"             INTEGER NOT NULL,
                "revision_created_at"  TEXT,
                "revision_author"      INTEGER,
                "revision_log"         TEXT,
                "title"                TEXT,
                "uuid"                 TEXT
            )
            SQL;
    }

    /** @return array{DBALDatabase, EntityType} */
    private function makeBlobSetup(string $entityTypeId = 'article'): array
    {
        $db = DBALDatabase::createSqlite();
        $conn = $db->getConnection();

        $type = new EntityType(
            id: $entityTypeId,
            label: 'Article',
            class: RevisionableArticleEntity::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'revision' => 'vid'],
            revisionable: true,
        );

        $conn->executeStatement($this->makePrimaryTableSql($entityTypeId, 'id'));
        $conn->executeStatement($this->makeRevisionTableBlobSql($entityTypeId, 'id'));

        return [$db, $type];
    }

    /** @return array{DBALDatabase, EntityType} */
    private function makeColumnSetup(string $entityTypeId = 'article'): array
    {
        $db = DBALDatabase::createSqlite();
        $conn = $db->getConnection();

        $type = new EntityType(
            id: $entityTypeId,
            label: 'Article',
            class: RevisionableArticleEntity::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'revision' => 'vid'],
            revisionable: true,
            primaryStorageBackend: 'sql-column',
        );

        $conn->executeStatement($this->makePrimaryTableSql($entityTypeId, 'id'));
        $conn->executeStatement($this->makeRevisionTableColumnSql($entityTypeId, 'id'));

        return [$db, $type];
    }

    /**
     * Insert a primary row with a given vid pointer. Returns the inserted id.
     */
    private function insertPrimary(
        DBALDatabase $db,
        string $table,
        int $vid,
        string $title = 'Hello',
    ): int {
        $conn = $db->getConnection();
        $conn->executeStatement(
            "INSERT INTO \"{$table}\" (vid, uuid, title, _data) VALUES (?, ?, ?, ?)",
            [$vid, 'test-uuid', $title, '{}'],
        );
        return (int) $conn->lastInsertId();
    }

    /**
     * Insert a revision row. Returns the auto-assigned vid.
     */
    private function insertRevision(
        DBALDatabase $db,
        string $table,
        int $entityId,
        string $createdAt,
        string $title = 'Hello',
        ?string $log = null,
        bool $blob = true,
    ): int {
        $conn = $db->getConnection();

        if ($blob) {
            $data = json_encode(['title' => $title], \JSON_THROW_ON_ERROR);
            $conn->executeStatement(
                "INSERT INTO \"{$table}__revision\" (id, revision_created_at, revision_log, _data)
                 VALUES (?, ?, ?, ?)",
                [$entityId, $createdAt, $log, $data],
            );
        } else {
            $conn->executeStatement(
                "INSERT INTO \"{$table}__revision\" (id, revision_created_at, revision_log, title, uuid)
                 VALUES (?, ?, ?, ?, ?)",
                [$entityId, $createdAt, $log, $title, 'test-uuid'],
            );
        }

        return (int) $conn->lastInsertId();
    }

    /**
     * Read the current vid from the primary table for the given entity id.
     */
    private function fetchVid(DBALDatabase $db, string $table, int $entityId): ?int
    {
        $rows = iterator_to_array(
            $db->query("SELECT vid FROM \"{$table}\" WHERE id = ?", [$entityId]),
        );
        if ($rows === []) {
            return null;
        }
        $val = ((array) $rows[0])['vid'] ?? null;
        return $val !== null ? (int) $val : null;
    }

    // -------------------------------------------------------------------------
    // T041: loadRevision — sql-blob
    // -------------------------------------------------------------------------

    #[Test]
    public function blob_loadRevision_returns_snapshot(): void
    {
        [$db, $type] = $this->makeBlobSetup();
        $conn = $db->getConnection();

        // Insert two revisions; primary points to vid 2 (latest).
        $v1 = $this->insertRevision($db, 'article', 99, '2026-01-01T00:00:00Z', 'First draft', 'initial', true);
        $v2 = $this->insertRevision($db, 'article', 99, '2026-01-02T00:00:00Z', 'Second draft', 'update', true);
        $conn->executeStatement('INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)', [99, $v2, 'u1', 'Second draft', '{}']);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        $v1Entity = $storage->loadRevision($type, $v1);
        $v2Entity = $storage->loadRevision($type, $v2);

        self::assertNotNull($v1Entity, 'vid 1 should be found');
        self::assertNotNull($v2Entity, 'vid 2 should be found');
        self::assertFalse($v1Entity->isCurrentRevision(), 'vid 1 is not the current revision');
        self::assertTrue($v2Entity->isCurrentRevision(), 'vid 2 IS the current revision');
        self::assertSame($v1, $v1Entity->revisionId(), 'vid 1 revisionId() matches');
        self::assertSame($v2, $v2Entity->revisionId(), 'vid 2 revisionId() matches');
    }

    #[Test]
    public function blob_loadRevision_returns_null_for_missing_vid(): void
    {
        [$db, $type] = $this->makeBlobSetup();

        $storage = new RevisionableSqlBlobStorage($db, $type);
        self::assertNull($storage->loadRevision($type, 99999));
    }

    // -------------------------------------------------------------------------
    // T041: loadRevision — sql-column
    // -------------------------------------------------------------------------

    #[Test]
    public function column_loadRevision_returns_snapshot(): void
    {
        [$db, $type] = $this->makeColumnSetup();
        $conn = $db->getConnection();

        $v1 = $this->insertRevision($db, 'article', 5, '2026-03-01T10:00:00Z', 'Column v1', null, false);
        $v2 = $this->insertRevision($db, 'article', 5, '2026-03-02T10:00:00Z', 'Column v2', null, false);
        $conn->executeStatement('INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)', [5, $v2, 'u2', 'Column v2', '{}']);

        $storage = new RevisionableSqlColumnStorage($db, $type);

        $old = $storage->loadRevision($type, $v1);
        $current = $storage->loadRevision($type, $v2);

        self::assertNotNull($old);
        self::assertNotNull($current);
        self::assertFalse($old->isCurrentRevision());
        self::assertTrue($current->isCurrentRevision());
    }

    // -------------------------------------------------------------------------
    // T042: listRevisions — descending order
    // -------------------------------------------------------------------------

    #[Test]
    public function blob_listRevisions_yields_in_descending_created_at_order(): void
    {
        [$db, $type] = $this->makeBlobSetup();
        $conn = $db->getConnection();

        $entityId = 7;
        $v1 = $this->insertRevision($db, 'article', $entityId, '2026-01-01T00:00:00Z', 'v1', null, true);
        $v2 = $this->insertRevision($db, 'article', $entityId, '2026-01-03T00:00:00Z', 'v2', null, true);
        $v3 = $this->insertRevision($db, 'article', $entityId, '2026-01-02T00:00:00Z', 'v3', null, true);
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, $v2, 'u7', 'v2', '{}'],
        );

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $storage = new RevisionableSqlBlobStorage($db, $type);
        $revisions = iterator_to_array($storage->listRevisions($entity));

        self::assertCount(3, $revisions);

        // Descending created_at: v2 (Jan 3) → v3 (Jan 2) → v1 (Jan 1).
        self::assertSame($v2, $revisions[0]->revisionId());
        self::assertSame($v3, $revisions[1]->revisionId());
        self::assertSame($v1, $revisions[2]->revisionId());
    }

    #[Test]
    public function column_listRevisions_yields_in_descending_created_at_order(): void
    {
        [$db, $type] = $this->makeColumnSetup();
        $conn = $db->getConnection();

        $entityId = 8;
        $v1 = $this->insertRevision($db, 'article', $entityId, '2026-02-01T00:00:00Z', 'cv1', null, false);
        $v2 = $this->insertRevision($db, 'article', $entityId, '2026-02-03T00:00:00Z', 'cv2', null, false);
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, $v2, 'u8', 'cv2', '{}'],
        );

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $storage = new RevisionableSqlColumnStorage($db, $type);
        $revisions = iterator_to_array($storage->listRevisions($entity));

        self::assertCount(2, $revisions);
        self::assertSame($v2, $revisions[0]->revisionId());
        self::assertSame($v1, $revisions[1]->revisionId());
    }

    #[Test]
    public function listRevisions_returns_empty_for_entity_with_no_revisions(): void
    {
        [$db, $type] = $this->makeBlobSetup();
        $conn = $db->getConnection();

        $entityId = 42;
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, null, 'u42', 'lonely', '{}'],
        );

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $storage = new RevisionableSqlBlobStorage($db, $type);
        $revisions = iterator_to_array($storage->listRevisions($entity));

        self::assertCount(0, $revisions);
    }

    // -------------------------------------------------------------------------
    // T043: setCurrentRevision — updates primary pointer + events
    // -------------------------------------------------------------------------

    #[Test]
    public function blob_setCurrentRevision_re_points_primary_and_dispatches_after_save(): void
    {
        [$db, $type] = $this->makeBlobSetup();
        $conn = $db->getConnection();

        $entityId = 10;
        $v1 = $this->insertRevision($db, 'article', $entityId, '2026-01-01T00:00:00Z', 'orig', null, true);
        $v2 = $this->insertRevision($db, 'article', $entityId, '2026-01-02T00:00:00Z', 'update', null, true);
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, $v2, 'u10', 'update', '{}'],
        );

        $dispatcher = new EventDispatcher();

        /** @var BeforeSaveEvent[] $beforeEvents */
        $beforeEvents = [];
        /** @var AfterSaveEvent[] $afterEvents */
        $afterEvents = [];

        $dispatcher->addListener(BeforeSaveEvent::class, static function (BeforeSaveEvent $e) use (&$beforeEvents): void {
            $beforeEvents[] = $e;
        });
        $dispatcher->addListener(AfterSaveEvent::class, static function (AfterSaveEvent $e) use (&$afterEvents): void {
            $afterEvents[] = $e;
        });

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $storage = new RevisionableSqlBlobStorage($db, $type, $dispatcher);

        // Primary currently points to v2; re-point to v1 (older revision).
        $storage->setCurrentRevision($entity, $v1);

        // Primary should now point to v1.
        self::assertSame($v1, $this->fetchVid($db, 'article', $entityId));

        // BeforeSaveEvent fired before the write.
        self::assertCount(1, $beforeEvents);
        self::assertFalse($beforeEvents[0]->isNewRevision());

        // AfterSaveEvent fired after successful commit.
        self::assertCount(1, $afterEvents);
        self::assertFalse($afterEvents[0]->isNewRevision());
    }

    #[Test]
    public function column_setCurrentRevision_re_points_primary_and_dispatches_after_save(): void
    {
        [$db, $type] = $this->makeColumnSetup();
        $conn = $db->getConnection();

        $entityId = 11;
        $v1 = $this->insertRevision($db, 'article', $entityId, '2026-01-01T00:00:00Z', 'c-orig', null, false);
        $v2 = $this->insertRevision($db, 'article', $entityId, '2026-01-02T00:00:00Z', 'c-update', null, false);
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, $v2, 'u11', 'c-update', '{}'],
        );

        $afterFired = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            AfterSaveEvent::class,
            static function () use (&$afterFired): void { $afterFired = true; },
        );

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $storage = new RevisionableSqlColumnStorage($db, $type, $dispatcher);
        $storage->setCurrentRevision($entity, $v1);

        self::assertSame($v1, $this->fetchVid($db, 'article', $entityId));
        self::assertTrue($afterFired, 'AfterSaveEvent must fire on setCurrentRevision success');
    }

    #[Test]
    public function setCurrentRevision_throws_for_nonexistent_revision(): void
    {
        [$db, $type] = $this->makeBlobSetup();
        $conn = $db->getConnection();

        $entityId = 20;
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, null, 'u20', 'exists', '{}'],
        );

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $storage->setCurrentRevision($entity, 999);
    }

    #[Test]
    public function after_save_does_not_fire_when_setCurrentRevision_fails(): void
    {
        [$db, $type] = $this->makeBlobSetup();
        $conn = $db->getConnection();

        $entityId = 21;
        $v1 = $this->insertRevision($db, 'article', $entityId, '2026-01-01T00:00:00Z', 'v1', null, true);
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, $v1, 'u21', 'v1', '{}'],
        );

        $afterFired = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            AfterSaveEvent::class,
            static function () use (&$afterFired): void { $afterFired = true; },
        );

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $storage = new RevisionableSqlBlobStorage($db, $type, $dispatcher);

        try {
            $storage->setCurrentRevision($entity, 999999);
        } catch (\InvalidArgumentException) {
            // Expected — nonexistent revision.
        }

        self::assertFalse($afterFired, 'AfterSaveEvent MUST NOT fire when setCurrentRevision fails');
    }

    // -------------------------------------------------------------------------
    // T044: SaveContext::withoutNewRevision
    // -------------------------------------------------------------------------

    #[Test]
    public function save_context_without_new_revision_returns_correct_flag(): void
    {
        $default = SaveContext::default();
        $noRevision = $default->withoutNewRevision();

        self::assertFalse($default->withoutNewRevision, 'default context has withoutNewRevision=false');
        self::assertTrue($noRevision->withoutNewRevision, 'withoutNewRevision() sets flag to true');

        // Immutability: original unchanged.
        self::assertNotSame($default, $noRevision);
    }

    // -------------------------------------------------------------------------
    // T045: RevisionPruner scaffold — disabled / no-op
    // -------------------------------------------------------------------------

    #[Test]
    public function revision_pruner_is_disabled_and_returns_no_op_report(): void
    {
        $pruner = new RevisionPruner();

        [$db, $type] = $this->makeBlobSetup();
        $conn = $db->getConnection();
        $entityId = 30;
        $v1 = $this->insertRevision($db, 'article', $entityId, '2026-01-01T00:00:00Z', 'prune-me', null, true);
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, $v1, 'u30', 'prune-me', '{}'],
        );

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $report = $pruner->prune($entity);

        self::assertInstanceOf(RevisionPruningReport::class, $report);
        self::assertSame(0, $report->pruned, 'No revisions pruned when pruner is disabled');
        self::assertNotEmpty($report->skipped, 'Skipped reasons list must be non-empty');
    }

    #[Test]
    public function revision_pruner_policy_is_accessible(): void
    {
        $policy = new RevisionPruningPolicy(keepLastN: 10);
        $pruner = new RevisionPruner(policy: $policy);

        self::assertSame(10, $pruner->policy()->keepLastN);
    }

    // -------------------------------------------------------------------------
    // T041 + T043: round-trip — loadRevision after setCurrentRevision
    // -------------------------------------------------------------------------

    #[Test]
    public function blob_round_trip_load_after_set_current(): void
    {
        [$db, $type] = $this->makeBlobSetup();
        $conn = $db->getConnection();

        $entityId = 50;
        $v1 = $this->insertRevision($db, 'article', $entityId, '2026-01-01T00:00:00Z', 'first', null, true);
        $v2 = $this->insertRevision($db, 'article', $entityId, '2026-01-02T00:00:00Z', 'second', null, true);
        $conn->executeStatement(
            'INSERT INTO "article" (id, vid, uuid, title, _data) VALUES (?, ?, ?, ?, ?)',
            [$entityId, $v2, 'u50', 'second', '{}'],
        );

        $entity = new RevisionableArticleEntity(['id' => $entityId]);
        $entity->enforceIsNew(false);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        // Before re-point: v1 is historical, v2 is current.
        $beforeV1 = $storage->loadRevision($type, $v1);
        self::assertFalse($beforeV1->isCurrentRevision());

        // Re-point to v1.
        $storage->setCurrentRevision($entity, $v1);

        // After re-point: v1 is current.
        $afterV1 = $storage->loadRevision($type, $v1);
        self::assertTrue($afterV1->isCurrentRevision());

        // v2 is no longer current.
        $afterV2 = $storage->loadRevision($type, $v2);
        self::assertFalse($afterV2->isCurrentRevision());
    }
}

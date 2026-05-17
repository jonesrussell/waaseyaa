<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\RevisionableSqlBlobStorage;
use Waaseyaa\EntityStorage\Schema\TranslationSchemaHandler;
use Waaseyaa\Field\FieldDefinition;

/**
 * Unit tests for the two-axis blob entry points on
 * {@see RevisionableSqlBlobStorage} (M-004 / WP02, T010 + T011).
 *
 * Round-trips:
 *   - {@see RevisionableSqlBlobStorage::writeTwoAxisTranslationRevision()}
 *     allocates a surrogate `vid` and inserts a row with a JSON `_data` blob
 *     into `<entity>__translation__revision`.
 *   - {@see RevisionableSqlBlobStorage::loadTwoAxisTranslationRevision()}
 *     reads that row back AND layers non-translatable values from
 *     `<entity>__revision` at the current primary vid (FR-005 fallback).
 *
 * @see contracts/composite-pk.md §7.1 (single-langcode translation save)
 * @see contracts/composite-pk.md §6.2 (FR-005 single-step fallback)
 */
#[CoversClass(RevisionableSqlBlobStorage::class)]
final class RevisionableSqlBlobStorageTwoAxisTest extends TestCase
{
    private function makeSqliteDatabase(): DBALDatabase
    {
        return new DBALDatabase(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
        );
    }

    private function makeTwoAxisType(string $id = 'teaching'): EntityType
    {
        return new EntityType(
            id: $id,
            label: ucfirst($id),
            class: ContentEntityBase::class,
            keys: [
                'id'               => 'tid',
                'uuid'             => 'uuid',
                'revision'         => 'vid',
                'langcode'         => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            revisionable: true,
            translatable: true,
            primaryStorageBackend: ReservedBackendIds::SQL_BLOB,
        );
    }

    /**
     * Bootstrap the two-axis schema using the WP02 entry point so the test
     * mirrors the production wiring path.
     */
    private function bootstrapSchema(DBALDatabase $db, EntityType $type): void
    {
        // Primary `<entity>` table (minimal — owned by SqlSchemaHandler in
        // production; we stub it here so revision FKs have a target).
        $db->schema()->createTable($type->id(), [
            'fields' => [
                'tid'              => ['type' => 'int', 'not null' => true],
                'uuid'             => ['type' => 'varchar', 'length' => 36],
                'vid'              => ['type' => 'int'],
                'langcode'         => ['type' => 'varchar', 'length' => 12],
                'default_langcode' => ['type' => 'varchar', 'length' => 12],
                '_data'            => ['type' => 'text'],
            ],
            'primary key' => ['tid'],
        ]);

        $fields = [
            new FieldDefinition(name: 'title', type: 'string', translatable: true),
            new FieldDefinition(name: 'body', type: 'text', translatable: true),
            new FieldDefinition(name: 'author_id', type: 'int'),
        ];

        $handler = new TranslationSchemaHandler($db);
        $handler->syncTwoAxis($type, $fields);
    }

    #[Test]
    public function write_two_axis_translation_revision_inserts_row_with_json_blob(): void
    {
        $db = $this->makeSqliteDatabase();
        $type = $this->makeTwoAxisType();
        $this->bootstrapSchema($db, $type);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        $vid = $storage->writeTwoAxisTranslationRevision(
            entityId: 42,
            langcode: 'en',
            translatableValues: ['title' => 'Hello', 'body' => 'World'],
            revisionAuthor: 7,
            revisionLog: 'initial',
        );

        self::assertGreaterThan(0, $vid);

        $row = null;
        foreach ($db->query('SELECT * FROM teaching__translation__revision WHERE vid = ?', [$vid]) as $r) {
            $row = (array) $r;
            break;
        }

        self::assertNotNull($row, 'inserted row must be retrievable by surrogate vid');
        self::assertSame('42', (string) $row['entity_id']);
        self::assertSame('en', $row['langcode']);
        self::assertSame(7, (int) $row['revision_author']);
        self::assertSame('initial', $row['revision_log']);

        $decoded = json_decode((string) $row['_data'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['title' => 'Hello', 'body' => 'World'], $decoded);
    }

    #[Test]
    public function load_two_axis_translation_revision_returns_null_for_missing_row(): void
    {
        $db = $this->makeSqliteDatabase();
        $type = $this->makeTwoAxisType();
        $this->bootstrapSchema($db, $type);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        self::assertNull(
            $storage->loadTwoAxisTranslationRevision(99, 'en', 1),
        );
    }

    #[Test]
    public function load_two_axis_translation_revision_round_trips_translatable_payload(): void
    {
        $db = $this->makeSqliteDatabase();
        $type = $this->makeTwoAxisType();
        $this->bootstrapSchema($db, $type);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        $vid = $storage->writeTwoAxisTranslationRevision(
            entityId: 42,
            langcode: 'oj',
            translatableValues: ['title' => 'Aaniin', 'body' => 'Boozhoo'],
        );

        $loaded = $storage->loadTwoAxisTranslationRevision(42, 'oj', $vid);
        self::assertIsArray($loaded);
        self::assertSame('Aaniin', $loaded['title']);
        self::assertSame('Boozhoo', $loaded['body']);
        self::assertSame('42', (string) $loaded['entity_id']);
        self::assertSame('oj', $loaded['langcode']);
        self::assertSame($vid, $loaded['vid']);
    }

    #[Test]
    public function load_two_axis_translation_revision_applies_fr_005_fallback(): void
    {
        // FR-005: non-translatable fields are stored once on the default-
        // langcode revision (in `<entity>__revision._data`). When loading a
        // non-default-langcode translation, the storage layer joins that
        // record so callers see a complete entity-shaped payload.
        $db = $this->makeSqliteDatabase();
        $type = $this->makeTwoAxisType();
        $this->bootstrapSchema($db, $type);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        // Seed: primary row with current vid=11 pointing at a revision row
        // that carries the non-translatable `author_id` and `priority` values.
        // Raw SQL avoids the `DBALInsert::execute()` → `lastInsertId()` call
        // path which raises `NoIdentityValue` when supplying an explicit
        // value for an AUTOINCREMENT column on SQLite (DBAL 4.x).
        iterator_to_array($db->query(
            'INSERT INTO teaching (tid, uuid, vid, langcode, default_langcode, _data)'
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            [42, 'u-42', 11, 'en', 'en', '{}'],
        ));

        iterator_to_array($db->query(
            'INSERT INTO teaching__revision (vid, tid, revision_created_at, _data)'
            . ' VALUES (?, ?, ?, ?)',
            [
                11,
                42,
                '2026-05-16T00:00:00+00:00',
                json_encode(['author_id' => 99, 'priority' => 7], \JSON_THROW_ON_ERROR),
            ],
        ));

        // The non-default-langcode (oj) translation only carries translatable
        // fields (title, body) in its `_data` blob.
        $vid = $storage->writeTwoAxisTranslationRevision(
            entityId: 42,
            langcode: 'oj',
            translatableValues: ['title' => 'Aaniin', 'body' => 'Boozhoo'],
        );

        $loaded = $storage->loadTwoAxisTranslationRevision(42, 'oj', $vid);

        self::assertIsArray($loaded);
        // Translatable values from the per-langcode blob.
        self::assertSame('Aaniin', $loaded['title']);
        self::assertSame('Boozhoo', $loaded['body']);
        // Non-translatable values layered from <entity>__revision (FR-005).
        self::assertSame(99, $loaded['author_id']);
        self::assertSame(7, $loaded['priority']);
    }

    #[Test]
    public function load_two_axis_translation_revision_with_no_current_vid_still_returns_translatable(): void
    {
        // Defensive: when the primary `<entity>` row is absent (e.g. testing
        // a translation revision in isolation), the fallback returns
        // gracefully empty and the caller still sees the translatable payload.
        $db = $this->makeSqliteDatabase();
        $type = $this->makeTwoAxisType();
        $this->bootstrapSchema($db, $type);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        $vid = $storage->writeTwoAxisTranslationRevision(
            entityId: 77,
            langcode: 'en',
            translatableValues: ['title' => 'Solo'],
        );

        $loaded = $storage->loadTwoAxisTranslationRevision(77, 'en', $vid);
        self::assertIsArray($loaded);
        self::assertSame('Solo', $loaded['title']);
        self::assertArrayNotHasKey('author_id', $loaded);
    }

    #[Test]
    public function write_two_axis_translation_revision_rejects_non_two_axis_entity_type(): void
    {
        $db = $this->makeSqliteDatabase();
        $type = new EntityType(
            id: 'post',
            label: 'Post',
            class: \stdClass::class,
            keys: ['id' => 'pid', 'revision' => 'vid'],
            revisionable: true,
            translatable: false,
            primaryStorageBackend: ReservedBackendIds::SQL_BLOB,
        );
        $storage = new RevisionableSqlBlobStorage($db, $type);

        $this->expectException(\InvalidArgumentException::class);
        $storage->writeTwoAxisTranslationRevision(1, 'en', []);
    }

    #[Test]
    public function write_two_axis_translation_revision_rejects_non_blob_backend(): void
    {
        $db = $this->makeSqliteDatabase();
        $type = new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: ContentEntityBase::class,
            keys: [
                'id'               => 'tid',
                'revision'         => 'vid',
                'langcode'         => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            revisionable: true,
            translatable: true,
            primaryStorageBackend: ReservedBackendIds::SQL_COLUMN,
        );
        $storage = new RevisionableSqlBlobStorage($db, $type);

        $this->expectException(\InvalidArgumentException::class);
        $storage->writeTwoAxisTranslationRevision(1, 'en', []);
    }

    #[Test]
    public function surrogate_vid_is_monotonic_within_a_langcode(): void
    {
        // Per contracts/composite-pk.md §5.2 — vids for a fixed
        // (entity_id, langcode) must be strictly increasing by created_at.
        $db = $this->makeSqliteDatabase();
        $type = $this->makeTwoAxisType();
        $this->bootstrapSchema($db, $type);

        $storage = new RevisionableSqlBlobStorage($db, $type);

        $vidA = $storage->writeTwoAxisTranslationRevision(42, 'en', ['title' => 'A']);
        $vidB = $storage->writeTwoAxisTranslationRevision(42, 'en', ['title' => 'B']);
        $vidC = $storage->writeTwoAxisTranslationRevision(42, 'en', ['title' => 'C']);

        self::assertGreaterThan($vidA, $vidB);
        self::assertGreaterThan($vidB, $vidC);
    }
}

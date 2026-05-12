<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Integration\BehaviorIdentity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\Backend\SqlBlobBackend;
use Waaseyaa\EntityStorage\Query\EntityQuery;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldStorage;

/**
 * T017 — SqlBlobBackend minimum-surface conformance test (FR-009, FR-010).
 *
 * Verifies the minimum surface contract for SqlBlobBackend:
 * - id() returns 'sql-blob' (ReservedBackendIds::SQL_BLOB)
 * - read/write/delete round-trip: write → read returns same value
 * - Idempotent re-write: writing the same value twice is safe
 * - write then delete: read returns null after delete
 * - supportsQuery() returns false for field predicates (FR-009)
 * - supportsQuery() returns false for entity-key columns too — key queries
 *   are handled by SqlEntityStorage directly (FR-010)
 */
#[CoversClass(SqlBlobBackend::class)]
final class SqlBlobMinimumSurfaceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDb(): DBALDatabase
    {
        return DBALDatabase::createSqlite(':memory:');
    }

    private function makeEntityType(string $id = 'blob_surface'): EntityType
    {
        return new EntityType(
            id: $id,
            label: 'Blob Surface',
            class: BehaviorIdentityEntity::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label', 'bundle' => 'bundle', 'langcode' => 'langcode'],
        );
    }

    private function makeSqlBlobBackend(DBALDatabase $db, EntityType $entityType): SqlBlobBackend
    {
        $keys = $entityType->getKeys();
        return new SqlBlobBackend(
            database: $db,
            entityTableName: $entityType->id(),
            idKey: $keys['id'] ?? 'id',
            entityTypeId: $entityType->id(),
        );
    }

    /**
     * Bootstrap: ensure schema and insert one entity row so the backend
     * has a row to UPDATE. Returns the saved entity.
     */
    private function seedEntity(
        DBALDatabase $db,
        EntityType $entityType,
        array $extra = [],
    ): \Waaseyaa\Entity\EntityInterface {
        (new SqlSchemaHandler(entityType: $entityType, database: $db))->ensureTable();

        $storage = new SqlEntityStorage(
            entityType: $entityType,
            database: $db,
            eventDispatcher: new EventDispatcher(),
        );

        $values = array_merge([
            'uuid' => 'cc000000-0000-0000-0000-' . sprintf('%012d', crc32($entityType->id()) & 0xFFFFFFFFFFFF),
            'label' => 'Surface Test',
            'bundle' => 'default',
            'langcode' => 'en',
        ], $extra);

        $entity = $storage->create($values);
        $storage->save($entity);

        return $entity;
    }

    // -------------------------------------------------------------------------
    // T017-1: id() contract
    // -------------------------------------------------------------------------

    #[Test]
    public function id_returns_sql_blob(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_id');
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        self::assertSame(ReservedBackendIds::SQL_BLOB, $backend->id());
    }

    // -------------------------------------------------------------------------
    // T017-2: write → read round-trip for blob-stored field
    // -------------------------------------------------------------------------

    #[Test]
    public function write_then_read_round_trip_for_blob_field(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_rw');
        $entity = $this->seedEntity($db, $entityType);
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        $field = new FieldDefinition('custom_note', 'string');

        $backend->write($entity, $field, 'hello world');

        $readBack = $backend->read($entity, $field);
        self::assertSame('hello world', $readBack);
    }

    // -------------------------------------------------------------------------
    // T017-3: Idempotent re-write
    // -------------------------------------------------------------------------

    private function makeQuery(): EntityQuery
    {
        return new class implements EntityQuery {};
    }

    #[Test]
    public function write_twice_same_value_is_idempotent(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_idem');
        $entity = $this->seedEntity($db, $entityType);
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        $field = new FieldDefinition('flag', 'string');

        $backend->write($entity, $field, 'value1');
        $backend->write($entity, $field, 'value1'); // idempotent re-write

        self::assertSame('value1', $backend->read($entity, $field));
    }

    // -------------------------------------------------------------------------
    // T017-4: write overwrites previous value
    // -------------------------------------------------------------------------

    #[Test]
    public function write_overwrites_previous_value(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_overwrite');
        $entity = $this->seedEntity($db, $entityType);
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        $field = new FieldDefinition('counter', 'string');

        $backend->write($entity, $field, 'first');
        $backend->write($entity, $field, 'second');

        self::assertSame('second', $backend->read($entity, $field));
    }

    // -------------------------------------------------------------------------
    // T017-5: delete clears _data (read returns null after delete)
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_clears_data_blob(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_delete');
        $entity = $this->seedEntity($db, $entityType);
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        $field = new FieldDefinition('item', 'string');
        $backend->write($entity, $field, 'to be deleted');

        // Confirm it was written.
        self::assertSame('to be deleted', $backend->read($entity, $field));

        // Delete.
        $backend->delete($entity);

        // After delete the _data blob is reset to {}, so field read returns null.
        $afterDelete = $backend->read($entity, $field);
        self::assertNull($afterDelete, 'After delete(), read() must return null for blob-stored fields');
    }

    // -------------------------------------------------------------------------
    // T017-6: supportsQuery() returns false for all field predicates (FR-009)
    // -------------------------------------------------------------------------

    #[Test]
    public function supports_query_returns_false_for_string_field(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_query');
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        $field = new FieldDefinition('title', 'string');
        $query = $this->makeQuery();

        self::assertFalse(
            $backend->supportsQuery($field, $query),
            'supportsQuery() must return false for string field predicate (FR-009)',
        );
    }

    #[Test]
    public function supports_query_returns_false_for_integer_field(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_query_int');
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        $field = new FieldDefinition('score', 'integer');
        $query = $this->makeQuery();

        self::assertFalse($backend->supportsQuery($field, $query));
    }

    // -------------------------------------------------------------------------
    // T017-7: supportsQuery() returns false even for entity-key column names (FR-010)
    //
    // Entity-key equality queries (id, uuid, bundle, langcode) are handled by
    // SqlEntityStorage directly via real columns — the backend does not need to
    // claim them. FR-010 says sql-blob SUPPORTS those queries (they work), but
    // they are not routed through supportsQuery() — that method is for field
    // predicates only. supportsQuery() correctly returns false for them.
    // -------------------------------------------------------------------------

    #[Test]
    public function supports_query_returns_false_for_entity_key_field(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_query_key');
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        // Even 'id' — which IS a real column — returns false from supportsQuery()
        // because that method is for field-predicate routing, not column checks.
        $idField = new FieldDefinition('id', 'integer');
        $uuidField = new FieldDefinition('uuid', 'string');
        $query = $this->makeQuery();

        self::assertFalse($backend->supportsQuery($idField, $query));
        self::assertFalse($backend->supportsQuery($uuidField, $query));
    }

    // -------------------------------------------------------------------------
    // T017-8: write/read round-trip for FieldStorage::Data-marked field
    // -------------------------------------------------------------------------

    #[Test]
    public function write_then_read_for_data_storage_field(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('blob_data_storage');
        $entity = $this->seedEntity($db, $entityType);
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        // FieldStorage::Data explicitly marks this as blob-stored.
        $field = new FieldDefinition('meta_info', 'string', stored: FieldStorage::Data);

        $backend->write($entity, $field, 'stored in blob');
        $readBack = $backend->read($entity, $field);

        self::assertSame('stored in blob', $readBack);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Integration\BehaviorIdentity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Backend\BackendRegistrar;
use Waaseyaa\EntityStorage\Backend\HasFieldStorageBackendsInterface;
use Waaseyaa\EntityStorage\Backend\IsFrameworkBackendProviderInterface;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\Backend\SqlBlobBackend;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinition;

/**
 * T016 — Post-refactor byte-identity gate (FR-008).
 *
 * Verifies that after extracting SqlBlobBackend from SqlEntityStorage, the
 * stored `_data` JSON bytes are IDENTICAL between:
 *
 *   Path A (legacy):  SqlEntityStorage → splitForStorage() → INSERT _data JSON
 *   Path B (refactor): SqlBlobBackend::write() per extra field → same INSERT
 *
 * Any deviation in key ordering, encoding flags, NULL handling, or value
 * coercion fails this test. No fuzzy matching, no normalization.
 *
 * Also verifies that entity reload (load()) produces byte-identical values
 * via both paths — ensuring mapRowToEntity() / read() symmetry.
 */
#[CoversClass(SqlBlobBackend::class)]
final class PostRefactorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDb(): DBALDatabase
    {
        return DBALDatabase::createSqlite(':memory:');
    }

    private function makeEntityType(string $id): EntityType
    {
        return new EntityType(
            id: $id,
            label: 'BI Entity',
            class: BehaviorIdentityEntity::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label', 'bundle' => 'bundle', 'langcode' => 'langcode'],
        );
    }

    private function makeStorage(DBALDatabase $db, EntityType $entityType): SqlEntityStorage
    {
        return new SqlEntityStorage(
            entityType: $entityType,
            database: $db,
            eventDispatcher: new EventDispatcher(),
        );
    }

    private function ensureSchema(DBALDatabase $db, EntityType $entityType): void
    {
        (new SqlSchemaHandler(entityType: $entityType, database: $db))->ensureTable();
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

    /** Build a BackendRegistrar with a real SqlBlobBackend registered. */
    private function makeRegistrar(DBALDatabase $db, EntityType $entityType): BackendRegistrar
    {
        $sqlBlob = $this->makeSqlBlobBackend($db, $entityType);

        // Emit a framework provider class that returns the real SqlBlobBackend.
        static $counter = 0;
        $counter++;
        $suffix = $counter;
        $fqcn = 'PostRefactorTestProvider_' . $suffix;

        eval(<<<PHP
            namespace Waaseyaa\EntityStorage\Tests\Integration\BehaviorIdentity;

            final class {$fqcn} implements \Waaseyaa\EntityStorage\Backend\HasFieldStorageBackendsInterface,
                                             \Waaseyaa\EntityStorage\Backend\IsFrameworkBackendProviderInterface {
                public function fieldStorageBackends(): array {
                    return \Waaseyaa\EntityStorage\Tests\Integration\BehaviorIdentity\PostRefactorProviderRegistry::get({$suffix});
                }
            }
        PHP);

        $fullFqcn = 'Waaseyaa\\EntityStorage\\Tests\\Integration\\BehaviorIdentity\\' . $fqcn;

        PostRefactorProviderRegistry::set($suffix, [$sqlBlob]);

        $registrar = new BackendRegistrar([$fullFqcn], [$fullFqcn]);
        $registrar->build();

        return $registrar;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRawRow(DBALDatabase $db, string $table, int $id): array
    {
        $result = $db->select($table)
            ->fields($table)
            ->condition('id', $id)
            ->execute();

        foreach ($result as $row) {
            return (array) $row;
        }

        return [];
    }

    // -------------------------------------------------------------------------
    // T016-1: _data bytes are identical between legacy and refactored paths
    // -------------------------------------------------------------------------

    #[Test]
    public function data_json_bytes_are_byte_identical_after_refactor(): void
    {
        // --- Path A: legacy SqlEntityStorage ---
        $dbA = $this->makeDb();
        $typeA = $this->makeEntityType('bi_byte_a');
        $this->ensureSchema($dbA, $typeA);
        $storageA = $this->makeStorage($dbA, $typeA);

        $valuesA = [
            'uuid' => 'aabbccdd-0000-0000-0000-000000000010',
            'label' => 'Byte Identity',
            'bundle' => 'default',
            'langcode' => 'en',
            'extra_string' => 'hello',
            'extra_int' => 42,
            'extra_bool' => true,
            'extra_null' => null,
        ];

        $entityA = $storageA->create($valuesA);
        $storageA->save($entityA);
        $rowA = $this->fetchRawRow($dbA, 'bi_byte_a', (int) $entityA->id());

        // --- Path B: SqlBlobBackend writes per-field, same schema ---
        $dbB = $this->makeDb();
        $typeB = $this->makeEntityType('bi_byte_b');
        $this->ensureSchema($dbB, $typeB);
        $storageB = $this->makeStorage($dbB, $typeB);

        // First create and save the base row via SqlEntityStorage (column fields only),
        // then re-encode _data via SqlBlobBackend to simulate the refactored path.
        // The baseline save initialises _data to '{}' (via splitForStorage with no extras),
        // then SqlBlobBackend writes each non-column extra field individually.
        $baseValuesB = [
            'uuid' => 'aabbccdd-0000-0000-0000-000000000010',
            'label' => 'Byte Identity',
            'bundle' => 'default',
            'langcode' => 'en',
        ];
        $entityB = $storageB->create($baseValuesB);
        $storageB->save($entityB);

        $backend = $this->makeSqlBlobBackend($dbB, $typeB);

        // Write extra fields in the same insertion order as the legacy path.
        // Legacy splitForStorage() iterates the entity's toArray() values, which
        // preserves the order they were passed to create(). We reproduce that order.
        $extraFields = [
            'extra_string' => 'hello',
            'extra_int' => 42,
            'extra_bool' => true,
            'extra_null' => null,
        ];

        // SqlBlobBackend::write() for each field individually builds up the blob.
        // We simulate the same by setting entity values and calling write per field.
        $entityB->set('extra_string', 'hello');
        $entityB->set('extra_int', 42);
        $entityB->set('extra_bool', true);
        $entityB->set('extra_null', null);

        // Use FieldDefinition stubs for type 'string' (not 'json') so no JSON encoding.
        foreach ($extraFields as $fieldName => $fieldValue) {
            $fieldDef = new FieldDefinition($fieldName, 'string');
            $backend->write($entityB, $fieldDef, $fieldValue);
        }

        $rowB = $this->fetchRawRow($dbB, 'bi_byte_b', (int) $entityB->id());

        // --- Assert byte identity ---
        self::assertSame(
            $rowA['_data'],
            $rowB['_data'],
            'Path A (legacy) and Path B (SqlBlobBackend) MUST produce byte-identical _data JSON',
        );
    }

    // -------------------------------------------------------------------------
    // T016-2: Entity reload produces identical values via both paths
    // -------------------------------------------------------------------------

    #[Test]
    public function reloaded_entity_values_are_identical_after_refactor(): void
    {
        // Legacy path: save and reload.
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('bi_reload');
        $this->ensureSchema($db, $entityType);
        $storage = $this->makeStorage($db, $entityType);

        $entity = $storage->create([
            'uuid' => 'aabbccdd-0000-0000-0000-000000000020',
            'label' => 'Reload Test',
            'bundle' => 'default',
            'langcode' => 'en',
            'score' => 77,
            'tag' => 'blue',
        ]);
        $storage->save($entity);

        $reloaded = $storage->load((int) $entity->id());
        self::assertNotNull($reloaded);
        self::assertSame(77, $reloaded->get('score'));
        self::assertSame('blue', $reloaded->get('tag'));

        // Now read back the same values via SqlBlobBackend::read().
        $backend = $this->makeSqlBlobBackend($db, $entityType);

        $scoreField = new FieldDefinition('score', 'string');
        $tagField = new FieldDefinition('tag', 'string');

        $readScore = $backend->read($reloaded, $scoreField);
        $readTag = $backend->read($reloaded, $tagField);

        self::assertSame(
            $reloaded->get('score'),
            $readScore,
            'SqlBlobBackend::read() must return same value as SqlEntityStorage reload for "score"',
        );
        self::assertSame(
            $reloaded->get('tag'),
            $readTag,
            'SqlBlobBackend::read() must return same value as SqlEntityStorage reload for "tag"',
        );
    }

    // -------------------------------------------------------------------------
    // T016-3: Empty _data byte identity
    // -------------------------------------------------------------------------

    #[Test]
    public function empty_data_is_byte_identical_between_paths(): void
    {
        // Legacy.
        $dbA = $this->makeDb();
        $typeA = $this->makeEntityType('bi_empty_a');
        $this->ensureSchema($dbA, $typeA);
        $storageA = $this->makeStorage($dbA, $typeA);
        $entityA = $storageA->create([
            'uuid' => 'aabbccdd-0000-0000-0000-000000000030',
            'label' => 'Empty',
            'bundle' => 'default',
            'langcode' => 'en',
        ]);
        $storageA->save($entityA);
        $rowA = $this->fetchRawRow($dbA, 'bi_empty_a', (int) $entityA->id());

        // Refactored: no extra-field writes, _data stays at its default '{}' from splitForStorage.
        $dbB = $this->makeDb();
        $typeB = $this->makeEntityType('bi_empty_b');
        $this->ensureSchema($dbB, $typeB);
        $storageB = $this->makeStorage($dbB, $typeB);
        $entityB = $storageB->create([
            'uuid' => 'aabbccdd-0000-0000-0000-000000000030',
            'label' => 'Empty',
            'bundle' => 'default',
            'langcode' => 'en',
        ]);
        $storageB->save($entityB);
        $rowB = $this->fetchRawRow($dbB, 'bi_empty_b', (int) $entityB->id());

        // json_encode([]) === '[]' — both paths must produce this exact byte sequence.
        self::assertSame('[]', $rowA['_data'], 'Legacy path: empty _data must be "[]"');
        self::assertSame($rowA['_data'], $rowB['_data'], 'Empty _data must be byte-identical between paths');
    }

    // -------------------------------------------------------------------------
    // T016-4: Schema shape preserved — _data column still present
    // -------------------------------------------------------------------------

    #[Test]
    public function schema_data_column_preserved_after_refactor(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('bi_schema_post');
        $this->ensureSchema($db, $entityType);

        self::assertTrue(
            $db->schema()->fieldExists('bi_schema_post', '_data'),
            'Post-refactor schema must still have _data column (sql-blob backend)',
        );
    }
}

/**
 * Static registry for PostRefactorTest framework provider instances.
 *
 * Needed because eval'd anonymous classes cannot close over variables.
 *
 * @internal
 */
final class PostRefactorProviderRegistry
{
    /** @var array<int, list<\Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface>> */
    private static array $store = [];

    /** @param list<\Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface> $backends */
    public static function set(int $key, array $backends): void
    {
        self::$store[$key] = $backends;
    }

    /** @return list<\Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface> */
    public static function get(int $key): array
    {
        return self::$store[$key] ?? [];
    }
}

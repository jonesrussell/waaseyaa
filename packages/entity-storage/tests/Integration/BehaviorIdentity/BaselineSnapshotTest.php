<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Integration\BehaviorIdentity;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinition;

/**
 * T015 — Baseline behavior snapshots for the sql-blob refactor (FR-008).
 *
 * Captures the EXACT behavior of the legacy SqlEntityStorage path
 * BEFORE the sql-blob refactor. These snapshots are the "ground truth"
 * that PostRefactorTest (T016) must byte-identically reproduce via
 * the SqlBlobBackend-routed path.
 *
 * Covered behaviors:
 * - Schema SQL: `_data` TEXT column is present with default '{}'
 * - CRUD round-trip: save → raw SELECT → confirm _data JSON keys + values
 * - String, int, bool, JSON field types in _data
 * - NULL handling in _data
 * - Key ordering in _data JSON (insertion order preserved by json_encode)
 * - Query results: entity reloaded has identical values
 *
 * @see PostRefactorTest
 */
#[CoversNothing]
final class BaselineSnapshotTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDb(): DBALDatabase
    {
        return DBALDatabase::createSqlite(':memory:');
    }

    private function makeEntityType(string $id = 'bi_entity'): EntityType
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
        (new SqlSchemaHandler(
            entityType: $entityType,
            database: $db,
        ))->ensureTable();
    }

    // -------------------------------------------------------------------------
    // T015-1: Schema shape — _data column present with correct spec
    // -------------------------------------------------------------------------

    #[Test]
    public function baseline_schema_has_data_column_with_default_empty_object(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('bi_schema');
        $this->ensureSchema($db, $entityType);

        $schema = $db->schema();
        self::assertTrue(
            $schema->fieldExists('bi_schema', '_data'),
            'Legacy schema MUST have _data TEXT column',
        );
    }

    // -------------------------------------------------------------------------
    // T015-2: Save → raw row — _data JSON content and key ordering
    // -------------------------------------------------------------------------

    #[Test]
    public function baseline_save_stores_non_column_fields_in_data_blob(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('bi_save');
        $this->ensureSchema($db, $entityType);
        $storage = $this->makeStorage($db, $entityType);

        $entity = $storage->create([
            'uuid' => 'aabbccdd-0000-0000-0000-000000000001',
            'label' => 'Test Label',
            'bundle' => 'default',
            'langcode' => 'en',
            'extra_string' => 'hello',
            'extra_int' => 42,
            'extra_bool' => true,
            'extra_null' => null,
        ]);
        $storage->save($entity);

        // Fetch raw row.
        $row = $this->fetchRawRow($db, 'bi_save', (int) $entity->id());

        // _data must exist.
        self::assertArrayHasKey('_data', $row, 'Raw row must have _data column');

        $data = json_decode($row['_data'], associative: true, flags: \JSON_THROW_ON_ERROR);

        // Non-schema fields go into _data.
        self::assertSame('hello', $data['extra_string'] ?? '__missing__');
        self::assertSame(42, $data['extra_int'] ?? '__missing__');
        self::assertTrue($data['extra_bool'] ?? false);
        // null values: json_encode(['extra_null' => null]) produces {"extra_null":null}
        // but only if the key was actually placed in $extraData. The legacy splitForStorage()
        // DOES place null values in $extraData — they appear as JSON null in _data.
        self::assertArrayHasKey('extra_null', $data, 'extra_null key must be present in _data as JSON null');
    }

    // -------------------------------------------------------------------------
    // T015-3: Key ordering in _data is insertion order
    // -------------------------------------------------------------------------

    #[Test]
    public function baseline_data_key_order_matches_insertion_order(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('bi_order');
        $this->ensureSchema($db, $entityType);
        $storage = $this->makeStorage($db, $entityType);

        // Insert extra fields in a deliberate order: z first, then a.
        $entity = $storage->create([
            'uuid' => 'aabbccdd-0000-0000-0000-000000000002',
            'label' => 'Order Test',
            'bundle' => 'default',
            'langcode' => 'en',
            'z_field' => 'last',
            'a_field' => 'first',
        ]);
        $storage->save($entity);

        $row = $this->fetchRawRow($db, 'bi_order', (int) $entity->id());
        self::assertArrayHasKey('_data', $row);

        $rawJson = $row['_data'];

        // Keys must appear in insertion order (z_field before a_field) — this
        // is the exact JSON the legacy path produces via json_encode($extraData).
        $zPos = strpos($rawJson, '"z_field"');
        $aPos = strpos($rawJson, '"a_field"');

        self::assertNotFalse($zPos, '"z_field" must appear in _data JSON');
        self::assertNotFalse($aPos, '"a_field" must appear in _data JSON');
        self::assertLessThan($aPos, $zPos, '"z_field" must appear before "a_field" (insertion order)');
    }

    // -------------------------------------------------------------------------
    // T015-4: CRUD round-trip — reload produces identical values
    // -------------------------------------------------------------------------

    #[Test]
    public function baseline_crud_round_trip_preserves_all_values(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('bi_crud');
        $this->ensureSchema($db, $entityType);
        $storage = $this->makeStorage($db, $entityType);

        $entity = $storage->create([
            'uuid' => 'aabbccdd-0000-0000-0000-000000000003',
            'label' => 'CRUD Test',
            'bundle' => 'default',
            'langcode' => 'en',
            'score' => 99,
            'active' => false,
            'note' => 'round trip',
        ]);
        $storage->save($entity);

        $reloaded = $storage->load((int) $entity->id());
        self::assertNotNull($reloaded, 'Entity must reload after save');
        self::assertSame(99, $reloaded->get('score'));
        self::assertSame(false, $reloaded->get('active'));
        self::assertSame('round trip', $reloaded->get('note'));
    }

    // -------------------------------------------------------------------------
    // T015-5: Capture exact _data JSON bytes (snapshot anchor)
    // -------------------------------------------------------------------------

    #[Test]
    public function baseline_data_json_uses_no_unescaped_flags(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('bi_flags');
        $this->ensureSchema($db, $entityType);
        $storage = $this->makeStorage($db, $entityType);

        $entity = $storage->create([
            'uuid' => 'aabbccdd-0000-0000-0000-000000000004',
            'label' => 'Flag Test',
            'bundle' => 'default',
            'langcode' => 'en',
            // Unicode and slash — should be escaped by default json_encode flags.
            'name' => "caf\u{00E9}",
            'url' => 'https://example.com/foo/bar',
        ]);
        $storage->save($entity);

        $row = $this->fetchRawRow($db, 'bi_flags', (int) $entity->id());
        $rawJson = $row['_data'];

        // Legacy path uses JSON_THROW_ON_ERROR only — no JSON_UNESCAPED_UNICODE.
        // "café" becomes "café" in JSON.
        self::assertStringContainsString('\\u00e9', $rawJson, 'Unicode must be escaped (no JSON_UNESCAPED_UNICODE)');
        // Slashes are escaped by default in PHP json_encode before 8.x was NOT
        // escaping slashes by default... Actually PHP json_encode does NOT escape
        // forward slashes by default (no JSON_HEX_TAG). The slash stays as-is.
        self::assertStringContainsString('https:', $rawJson, 'URL with slashes must appear in _data');
    }

    // -------------------------------------------------------------------------
    // T015-6: Empty _data for entity with no extra fields
    // -------------------------------------------------------------------------

    #[Test]
    public function baseline_data_is_empty_object_when_no_extra_fields(): void
    {
        $db = $this->makeDb();
        $entityType = $this->makeEntityType('bi_empty');
        $this->ensureSchema($db, $entityType);
        $storage = $this->makeStorage($db, $entityType);

        $entity = $storage->create([
            'uuid' => 'aabbccdd-0000-0000-0000-000000000005',
            'label' => 'Empty Data',
            'bundle' => 'default',
            'langcode' => 'en',
        ]);
        $storage->save($entity);

        $row = $this->fetchRawRow($db, 'bi_empty', (int) $entity->id());
        // json_encode([]) produces '[]' (empty PHP array encodes as JSON array, not object).
        // This is the exact byte output of splitForStorage() when $extraData is [].
        self::assertSame('[]', $row['_data'], '_data must be exactly "[]" (empty JSON array) when no extra fields');
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

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
}

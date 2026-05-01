<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Exception\UnknownFieldException;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;
use Waaseyaa\Field\FieldStorage;

/**
 * Empirical gates for alpha.150's two bundle-substrate fixes:
 *
 *   - Bug C: SqlEntityStorage::getQuery() must forward $this->fieldRegistry
 *     into SqlEntityQuery, otherwise bundle-scoped condition fields silently
 *     drop the JOIN and return wrong results (or throw at resolve time).
 *
 *   - Gap D: a core field marked FieldStorage::Data must resolve via
 *     json_extract(_data, ...) instead of triggering UnknownFieldException
 *     in SqlEntityQuery::routeFields(), and SqlEntityStorage::splitForStorage
 *     must route the value into _data on save even if a legacy column exists.
 *
 * Lives at the unit layer because it exercises one storage instance against
 * an in-memory DBAL — no kernel boot needed.
 */
#[CoversNothing]
final class SqlEntityStorageBundleQueryRoutingTest extends TestCase
{
    private DBALDatabase $database;
    private FieldDefinitionRegistry $registry;
    private SqlEntityStorage $storage;
    private EntityType $entityType;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite(':memory:');
        $this->registry = new FieldDefinitionRegistry();

        // The widget's core fields (notably the FieldStorage::Data-stored
        // `status`) and the bundleEntityType slot are not expressible via
        // `#[Field]` in M1 — `#[Field]` doesn't carry a `stored:` parameter,
        // and bundle attributes are deferred to a follow-on mission. We
        // therefore build the minimal EntityType through the public
        // constructor and register the data-stored core field directly into
        // the FieldDefinitionRegistry, which is the runtime contract the
        // SqlEntityStorage / SqlSchemaHandler bundle-substrate code consults.
        $this->entityType = new EntityType(
            id: 'widget',
            label: 'Widget',
            class: TestRoutingWidget::class,
            keys: [
                'id' => 'wid',
                'uuid' => 'uuid',
                'bundle' => 'type',
                'label' => 'name',
                'langcode' => 'langcode',
            ],
            bundleEntityType: 'widget_type',
        );

        $this->registry->registerCoreFields(
            $this->entityType->id(),
            [
                'status' => new FieldDefinition(
                    name: 'status',
                    type: 'integer',
                    targetEntityTypeId: 'widget',
                    defaultValue: 1,
                    label: 'Status',
                    stored: FieldStorage::Data,
                ),
            ],
        );

        $this->registry->registerBundleFields('widget', 'gizmo', [
            'gizmo_code' => new FieldDefinition(
                name: 'gizmo_code',
                type: 'string',
                targetEntityTypeId: 'widget',
                targetBundle: 'gizmo',
            ),
        ]);

        $schemaHandler = new SqlSchemaHandler(
            $this->entityType,
            $this->database,
            $this->registry,
        );
        $schemaHandler->ensureTable();

        $this->storage = new SqlEntityStorage(
            $this->entityType,
            $this->database,
            new EventDispatcher(),
            $this->registry,
        );
    }

    #[Test]
    public function getQueryForwardsFieldRegistryEnablingBundleScopedConditions(): void
    {
        $matching = new TestRoutingWidget([
            'name' => 'Match',
            'type' => 'gizmo',
            'gizmo_code' => 'X-1',
        ]);
        $other = new TestRoutingWidget([
            'name' => 'Other',
            'type' => 'gizmo',
            'gizmo_code' => 'Y-2',
        ]);

        $this->storage->save($matching);
        $this->storage->save($other);

        $ids = $this->storage->getQuery()
            ->condition('gizmo_code', 'X-1')
            ->execute();

        self::assertSame([$matching->id()], $ids);
    }

    #[Test]
    public function dataStoredCoreFieldResolvesViaJsonExtractInsteadOfThrowing(): void
    {
        $entity = new TestRoutingWidget([
            'name' => 'Stored Hint',
            'type' => 'gizmo',
            'status' => 1,
            'gizmo_code' => 'Z-3',
        ]);
        $this->storage->save($entity);

        $ids = $this->storage->getQuery()
            ->condition('status', 1)
            ->execute();

        self::assertContains($entity->id(), $ids);
    }

    #[Test]
    public function trulyUnknownFieldStillThrowsUnknownFieldException(): void
    {
        $this->expectException(UnknownFieldException::class);

        $this->storage->getQuery()
            ->condition('totally_made_up_field', 'whatever')
            ->execute();
    }

    #[Test]
    public function dataStoredCoreFieldLandsInDataBlobNotColumn(): void
    {
        $entity = new TestRoutingWidget([
            'name' => 'Persisted',
            'type' => 'gizmo',
            'status' => 1,
            'gizmo_code' => 'W-9',
        ]);
        $this->storage->save($entity);

        $row = $this->database->getConnection()->fetchAssociative(
            'SELECT _data FROM "widget" WHERE wid = :wid',
            ['wid' => $entity->id()],
        );
        self::assertIsArray($row);

        $data = json_decode((string) $row['_data'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('status', $data);
        self::assertSame(1, $data['status']);

        $columns = $this->database->getConnection()->fetchAllAssociative('PRAGMA table_info("widget")');
        $columnNames = array_column($columns, 'name');
        self::assertNotContains('status', $columnNames, 'status must not be materialized as a base column under FieldStorage::Data.');
    }

    /**
     * WP04 #1257 (K2 — read/write symmetry for FieldStorage::Data).
     *
     * Reproduces the asymmetric case from #1308: a legacy `status` column
     * lingers in the schema after the field's storage hint was migrated to
     * FieldStorage::Data. The write path (`SqlEntityStorage::splitForStorage()`)
     * already consults the registry hint and routes new writes to `_data`.
     * The read path (`SqlEntityQuery::resolveField()`) MUST consult the same
     * registry hint and resolve via `json_extract(_data, ...)`, never the
     * stale column. Otherwise reads silently return pre-migration column
     * values while writes go elsewhere.
     */
    #[Test]
    public function legacyColumnForDataStoredFieldDoesNotShadowJsonExtract(): void
    {
        // Simulate a legacy column lingering after the field migrated to
        // FieldStorage::Data. ensureTable() correctly does not materialize
        // `status` (verified by dataStoredCoreFieldLandsInDataBlobNotColumn);
        // we ALTER it back in to model the pre-migration legacy state.
        $this->database->getConnection()->executeStatement(
            'ALTER TABLE "widget" ADD COLUMN status INTEGER',
        );

        $matchEntity = new TestRoutingWidget([
            'name' => 'Match',
            'type' => 'gizmo',
            'status' => 1,
            'gizmo_code' => 'X-1',
        ]);
        $other = new TestRoutingWidget([
            'name' => 'Other',
            'type' => 'gizmo',
            'status' => 2,
            'gizmo_code' => 'Y-2',
        ]);

        $this->storage->save($matchEntity);
        $this->storage->save($other);

        // Confirm the write side is already symmetric: both rows have NULL in
        // the legacy column and their actual status in `_data`.
        $rows = $this->database->getConnection()->fetchAllAssociative(
            'SELECT wid, status, _data FROM "widget" ORDER BY wid',
        );
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertNull($row['status'], 'Write path must not populate the legacy column when FieldStorage::Data is set.');
        }

        // Now poison the legacy column with stale values that, if the read
        // path consults the column, would either match the wrong row or no
        // row at all.
        $this->database->getConnection()->executeStatement(
            'UPDATE "widget" SET status = 99',
        );

        $ids = $this->storage->getQuery()
            ->condition('status', 1)
            ->execute();

        self::assertSame(
            [$matchEntity->id()],
            $ids,
            'Read path must consult the FieldStorage::Data registry hint and resolve via json_extract on `_data`, '
            . 'never the lingering legacy column. Reading the column would return either no match (column = 99) or the wrong row.',
        );
    }
}

/**
 * Test-only entity that participates in the registry routing but has no
 * production semantics. Lives in the same file to keep the contract local.
 */
#[ContentEntityType(id: 'widget')]
#[ContentEntityKeys(id: 'wid', uuid: 'uuid', bundle: 'type', label: 'name', langcode: 'langcode')]
final class TestRoutingWidget extends ContentEntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct($values);
    }
}

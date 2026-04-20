<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
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
            fieldDefinitions: [
                'status' => [
                    'type' => 'integer',
                    'default' => 1,
                    'stored' => FieldStorage::Data,
                    'label' => 'Status',
                ],
            ],
        );

        $this->registry->registerCoreFields(
            $this->entityType->id(),
            $this->entityType->getFieldDefinitions(),
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
}

/**
 * Test-only entity that participates in the registry routing but has no
 * production semantics. Lives in the same file to keep the contract local.
 */
final class TestRoutingWidget extends ContentEntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct(
            $values,
            'widget',
            [
                'id' => 'wid',
                'uuid' => 'uuid',
                'bundle' => 'type',
                'label' => 'name',
                'langcode' => 'langcode',
            ],
        );
    }
}

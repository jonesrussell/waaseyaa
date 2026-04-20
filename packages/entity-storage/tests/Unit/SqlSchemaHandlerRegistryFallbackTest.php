<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;

/**
 * Pins the alpha.148 regression shape.
 *
 * The bundle-subtable materialization loop has two wire-ups that must
 * agree:
 *   1. shouldProcessBundles() — fieldRegistry non-null AND bundleEntityType non-null.
 *   2. registeredBundlesFor() — falls back to FieldDefinitionRegistry::bundleNamesFor()
 *      when the explicit bundleEnumerator is null.
 *
 * In alpha.148, (2) was inverted: the loop ran only when an explicit
 * bundleEnumerator was supplied. addBundleFields()-registered bundles
 * (which populate the registry but do not supply an enumerator) silently
 * stopped producing subtables. This test asserts the post-fix behavior
 * at unit level so a refactor cannot re-orphan the registry fallback
 * without turning this test red.
 */
#[CoversClass(SqlSchemaHandler::class)]
final class SqlSchemaHandlerRegistryFallbackTest extends TestCase
{
    #[Test]
    public function registryPopulationDrivesSubtableMaterializationWithoutEnumerator(): void
    {
        $database = DBALDatabase::createSqlite(':memory:');
        $entityType = new EntityType(
            id: 'widget',
            label: 'Widget',
            class: \stdClass::class,
            keys: ['id' => 'wid', 'uuid' => 'uuid', 'bundle' => 'type', 'label' => 'name'],
            bundleEntityType: 'widget_type',
        );

        $registry = new FieldDefinitionRegistry();
        $registry->registerBundleFields('widget', 'gizmo', [
            'gizmo_code' => new FieldDefinition(
                name: 'gizmo_code',
                type: 'string',
                targetEntityTypeId: 'widget',
                targetBundle: 'gizmo',
            ),
        ]);

        $handler = new SqlSchemaHandler(
            entityType: $entityType,
            database: $database,
            fieldRegistry: $registry,
            bundleEnumerator: null,
        );
        $handler->ensureTable();

        $connection = $database->getConnection();
        $subtableExists = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'widget__gizmo'",
        );

        self::assertSame(
            1,
            $subtableExists,
            'SqlSchemaHandler must materialize bundle subtables using FieldDefinitionRegistry::bundleNamesFor() when no explicit bundleEnumerator is supplied. This is the registry-fallback branch that alpha.148 orphaned.',
        );
    }

    #[Test]
    public function noBundleEntityTypeSkipsBundleLoopEvenWithPopulatedRegistry(): void
    {
        $database = DBALDatabase::createSqlite(':memory:');
        $entityType = new EntityType(
            id: 'flat_thing',
            label: 'Flat Thing',
            class: \stdClass::class,
            keys: ['id' => 'id', 'uuid' => 'uuid'],
        );

        $registry = new FieldDefinitionRegistry();
        $registry->registerBundleFields('flat_thing', 'phantom', [
            'ghost_field' => new FieldDefinition(
                name: 'ghost_field',
                type: 'string',
                targetEntityTypeId: 'flat_thing',
                targetBundle: 'phantom',
            ),
        ]);

        $handler = new SqlSchemaHandler(
            entityType: $entityType,
            database: $database,
            fieldRegistry: $registry,
            bundleEnumerator: null,
        );
        $handler->ensureTable();

        $connection = $database->getConnection();
        $unwanted = $connection->fetchAllAssociative(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'flat_thing__%'",
        );

        self::assertSame(
            [],
            $unwanted,
            'shouldProcessBundles() must return false when the entity type has no bundleEntityType, regardless of registry contents.',
        );
    }

    #[Test]
    public function nullRegistrySkipsBundleLoopEvenWithBundleEntityType(): void
    {
        $database = DBALDatabase::createSqlite(':memory:');
        $entityType = new EntityType(
            id: 'orphan',
            label: 'Orphan',
            class: \stdClass::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'bundle' => 'type'],
            bundleEntityType: 'orphan_type',
        );

        $handler = new SqlSchemaHandler(
            entityType: $entityType,
            database: $database,
            fieldRegistry: null,
            bundleEnumerator: null,
        );
        $handler->ensureTable();

        $connection = $database->getConnection();
        $baseExists = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'orphan'",
        );
        $unwanted = $connection->fetchAllAssociative(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'orphan__%'",
        );

        self::assertSame(1, $baseExists);
        self::assertSame(
            [],
            $unwanted,
            'shouldProcessBundles() must return false when fieldRegistry is null, matching pre-bundle-scoped behavior.',
        );
    }
}

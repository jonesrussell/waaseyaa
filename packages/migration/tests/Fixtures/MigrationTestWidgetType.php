<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Fixtures;

use Waaseyaa\Entity\EntityType;

/**
 * Test-fixture helper: emits the two `EntityType` definitions used by the
 * EntityDestination integration tests.
 *
 * Two flavours:
 * - `migration_test_widget` (non-revisionable) → exercises FR-018..FR-022 + FR-031
 *   round-trip.
 * - `migration_test_revisionable_widget` (revisionable) → exercises FR-023 +
 *   FR-031 skip semantics against the revision table.
 *
 * @internal Test fixture only.
 */
final class MigrationTestWidgetType
{
    public static function nonRevisionable(): EntityType
    {
        return new EntityType(
            id: 'migration_test_widget',
            label: 'Migration Test Widget',
            class: MigrationTestWidget::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
        );
    }

    public static function revisionable(): EntityType
    {
        return new EntityType(
            id: 'migration_test_revisionable_widget',
            label: 'Migration Test Revisionable Widget',
            class: MigrationTestRevisionableWidget::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'title',
                'revision' => 'revision_id',
            ],
            revisionable: true,
        );
    }
}

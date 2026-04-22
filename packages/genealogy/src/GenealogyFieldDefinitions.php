<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy;

use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldStorage;

/**
 * Core {@see FieldDefinition} objects for genealogy entity types (registry boundary).
 */
final class GenealogyFieldDefinitions
{
    /**
     * @return array<string, FieldDefinition>
     */
    public static function treeFields(): array
    {
        $et = 'genealogy_tree';

        return [
            'display_name' => new FieldDefinition(
                name: 'display_name',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 0],
                targetEntityTypeId: $et,
                defaultValue: null,
                label: 'Display name',
                description: 'Human-facing tree title.',
                required: true,
                readOnly: false,
                stored: FieldStorage::Column,
            ),
            'owner_uid' => new FieldDefinition(
                name: 'owner_uid',
                type: 'integer',
                cardinality: 1,
                settings: ['weight' => 1, 'not_null' => true],
                targetEntityTypeId: $et,
                defaultValue: null,
                label: 'Owner user ID',
                description: 'Account that owns this tree workspace.',
                required: true,
                readOnly: false,
                stored: FieldStorage::Column,
            ),
            'status' => new FieldDefinition(
                name: 'status',
                type: 'boolean',
                cardinality: 1,
                settings: ['weight' => 10],
                targetEntityTypeId: $et,
                defaultValue: false,
                label: 'Published',
                description: 'Workflow-style visibility flag; defaults off (private-by-default).',
                required: false,
                readOnly: false,
                stored: FieldStorage::Column,
            ),
        ];
    }

    /**
     * @return array<string, FieldDefinition>
     */
    public static function personFields(): array
    {
        $et = 'genealogy_person';

        return [
            'display_name' => new FieldDefinition(
                name: 'display_name',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 0],
                targetEntityTypeId: $et,
                label: 'Display name',
                required: true,
                stored: FieldStorage::Column,
            ),
            'given_name' => new FieldDefinition(
                name: 'given_name',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 1],
                targetEntityTypeId: $et,
                label: 'Given name',
                stored: FieldStorage::Column,
            ),
            'family_name' => new FieldDefinition(
                name: 'family_name',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 2],
                targetEntityTypeId: $et,
                label: 'Family name',
                stored: FieldStorage::Column,
            ),
            'birth_date' => new FieldDefinition(
                name: 'birth_date',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 3],
                targetEntityTypeId: $et,
                label: 'Birth date',
                stored: FieldStorage::Column,
            ),
            'death_date' => new FieldDefinition(
                name: 'death_date',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 4],
                targetEntityTypeId: $et,
                label: 'Death date',
                stored: FieldStorage::Column,
            ),
            'is_living' => new FieldDefinition(
                name: 'is_living',
                type: 'boolean',
                cardinality: 1,
                settings: ['weight' => 5],
                targetEntityTypeId: $et,
                defaultValue: true,
                label: 'Living (manual)',
                description: 'When true, stricter visibility applies for non-owners. Default true (conservative) when dates are unknown.',
                stored: FieldStorage::Column,
            ),
            'tree_id' => new FieldDefinition(
                name: 'tree_id',
                type: 'integer',
                cardinality: 1,
                settings: ['weight' => 6, 'not_null' => false],
                targetEntityTypeId: $et,
                label: 'Tree',
                description: 'Owning genealogy_tree entity id.',
                stored: FieldStorage::Column,
            ),
            'deleted_at' => new FieldDefinition(
                name: 'deleted_at',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 9, 'length' => 32],
                targetEntityTypeId: $et,
                defaultValue: '',
                label: 'Deleted at',
                description: 'Non-empty ISO-ish tombstone timestamp when soft-deleted.',
                stored: FieldStorage::Column,
            ),
            'status' => new FieldDefinition(
                name: 'status',
                type: 'boolean',
                cardinality: 1,
                settings: ['weight' => 10],
                targetEntityTypeId: $et,
                defaultValue: false,
                label: 'Published',
                stored: FieldStorage::Column,
            ),
        ];
    }

    /**
     * @return array<string, FieldDefinition>
     */
    public static function familyFields(): array
    {
        $et = 'genealogy_family';

        return [
            'display_name' => new FieldDefinition(
                name: 'display_name',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 0],
                targetEntityTypeId: $et,
                label: 'Display name',
                required: true,
                stored: FieldStorage::Column,
            ),
            'tree_id' => new FieldDefinition(
                name: 'tree_id',
                type: 'integer',
                cardinality: 1,
                settings: ['weight' => 1, 'not_null' => false],
                targetEntityTypeId: $et,
                label: 'Tree',
                stored: FieldStorage::Column,
            ),
            'deleted_at' => new FieldDefinition(
                name: 'deleted_at',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 9, 'length' => 32],
                targetEntityTypeId: $et,
                defaultValue: '',
                label: 'Deleted at',
                stored: FieldStorage::Column,
            ),
            'status' => new FieldDefinition(
                name: 'status',
                type: 'boolean',
                cardinality: 1,
                settings: ['weight' => 10],
                targetEntityTypeId: $et,
                defaultValue: false,
                label: 'Published',
                stored: FieldStorage::Column,
            ),
        ];
    }

    /**
     * @return array<string, FieldDefinition>
     */
    public static function eventFields(): array
    {
        $et = 'genealogy_event';

        return [
            'display_name' => new FieldDefinition(
                name: 'display_name',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 0],
                targetEntityTypeId: $et,
                label: 'Display name',
                required: true,
                stored: FieldStorage::Column,
            ),
            'event_type' => new FieldDefinition(
                name: 'event_type',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 1],
                targetEntityTypeId: $et,
                label: 'Event type',
                stored: FieldStorage::Column,
            ),
            'event_date' => new FieldDefinition(
                name: 'event_date',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 2],
                targetEntityTypeId: $et,
                label: 'Event date',
                stored: FieldStorage::Column,
            ),
            'tree_id' => new FieldDefinition(
                name: 'tree_id',
                type: 'integer',
                cardinality: 1,
                settings: ['weight' => 3, 'not_null' => false],
                targetEntityTypeId: $et,
                label: 'Tree',
                stored: FieldStorage::Column,
            ),
            'deleted_at' => new FieldDefinition(
                name: 'deleted_at',
                type: 'string',
                cardinality: 1,
                settings: ['weight' => 9, 'length' => 32],
                targetEntityTypeId: $et,
                defaultValue: '',
                label: 'Deleted at',
                stored: FieldStorage::Column,
            ),
            'status' => new FieldDefinition(
                name: 'status',
                type: 'boolean',
                cardinality: 1,
                settings: ['weight' => 10],
                targetEntityTypeId: $et,
                defaultValue: false,
                label: 'Published',
                stored: FieldStorage::Column,
            ),
        ];
    }
}

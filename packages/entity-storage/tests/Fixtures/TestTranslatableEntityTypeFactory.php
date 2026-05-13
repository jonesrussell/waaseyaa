<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\Field\FieldDefinition;

/**
 * Companion factory for {@see TestTranslatableEntity} (WP13).
 *
 * Centralises the EntityType + FieldDefinition wiring so the sql-blob and
 * sql-column contract subclasses share one fixture shape.
 *
 * Translatable fields: `title` (string), `body` (text), `description` (text).
 * Non-translatable fields: `created_at` (timestamp), `author_id` (entity_reference).
 *
 * The factory accepts a primary-storage-backend id so callers can flip the
 * EntityType between sql-blob (default) and sql-column.
 *
 * @internal Test fixture for the translatable contract suite.
 */
final class TestTranslatableEntityTypeFactory
{
    public const string ENTITY_TYPE_ID = 'test_translatable_entity';

    private function __construct() {}

    /**
     * @return array<string, FieldDefinition>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'title' => new FieldDefinition(
                name: 'title',
                type: 'string',
                translatable: true,
            ),
            'body' => new FieldDefinition(
                name: 'body',
                type: 'text',
                translatable: true,
            ),
            'description' => new FieldDefinition(
                name: 'description',
                type: 'text',
                translatable: true,
            ),
            'created_at' => new FieldDefinition(
                name: 'created_at',
                type: 'integer',
                translatable: false,
            ),
            'author_id' => new FieldDefinition(
                name: 'author_id',
                type: 'string',
                translatable: false,
            ),
        ];
    }

    /**
     * Build the EntityType used by the WP13 translatable contract suite.
     */
    public static function build(string $primaryStorageBackend = ReservedBackendIds::SQL_BLOB): EntityType
    {
        return new EntityType(
            id: self::ENTITY_TYPE_ID,
            label: 'Test Translatable Entity',
            class: TestTranslatableEntity::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            translatable: true,
            _fieldDefinitions: self::fieldDefinitions(),
            primaryStorageBackend: $primaryStorageBackend,
        );
    }
}

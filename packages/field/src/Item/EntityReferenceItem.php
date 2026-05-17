<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Item;

use Waaseyaa\Field\Attribute\FieldType;
use Waaseyaa\Field\FieldItemBase;

#[FieldType(
    id: 'entity_reference',
    label: 'Entity Reference',
    description: 'A field containing a reference to another entity.',
    category: 'reference',
    defaultCardinality: 1,
)]
/**
 * @api
 */
final class EntityReferenceItem extends FieldItemBase
{
    public static function propertyDefinitions(): array
    {
        return [
            'target_id' => 'integer',
            'target_type' => 'string',
        ];
    }

    public static function mainPropertyName(): string
    {
        return 'target_id';
    }

    public static function schema(): array
    {
        return [
            'target_id' => ['type' => 'int'],
            'target_type' => ['type' => 'varchar', 'length' => 255],
        ];
    }

    public static function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'target_id' => ['type' => 'integer'],
                'target_type' => ['type' => 'string'],
            ],
        ];
    }
}

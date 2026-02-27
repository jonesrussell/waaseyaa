<?php

declare(strict_types=1);

namespace Aurora\Field\Item;

use Aurora\Field\Attribute\FieldType;
use Aurora\Field\FieldItemBase;

#[FieldType(
    id: 'text',
    label: 'Text',
    description: 'A field containing formatted text with a text format.',
    category: 'general',
    defaultCardinality: 1,
)]
class TextItem extends FieldItemBase
{
    public static function propertyDefinitions(): array
    {
        return [
            'value' => 'string',
            'format' => 'string',
        ];
    }

    public static function mainPropertyName(): string
    {
        return 'value';
    }

    public static function schema(): array
    {
        return [
            'value' => ['type' => 'text'],
            'format' => ['type' => 'varchar', 'length' => 255],
        ];
    }

    public static function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'string'],
                'format' => ['type' => 'string'],
            ],
        ];
    }
}

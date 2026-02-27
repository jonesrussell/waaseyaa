<?php

declare(strict_types=1);

namespace Aurora\Field\Item;

use Aurora\Field\Attribute\FieldType;
use Aurora\Field\FieldItemBase;

#[FieldType(
    id: 'string',
    label: 'String',
    description: 'A field containing a plain string value.',
    category: 'general',
    defaultCardinality: 1,
)]
class StringItem extends FieldItemBase
{
    public static function propertyDefinitions(): array
    {
        return [
            'value' => 'string',
        ];
    }

    public static function mainPropertyName(): string
    {
        return 'value';
    }

    public static function schema(): array
    {
        return [
            'value' => ['type' => 'varchar', 'length' => 255],
        ];
    }

    public static function jsonSchema(): array
    {
        return ['type' => 'string', 'maxLength' => 255];
    }
}

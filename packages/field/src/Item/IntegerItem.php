<?php

declare(strict_types=1);

namespace Aurora\Field\Item;

use Aurora\Field\Attribute\FieldType;
use Aurora\Field\FieldItemBase;

#[FieldType(
    id: 'integer',
    label: 'Integer',
    description: 'A field containing an integer value.',
    category: 'general',
    defaultCardinality: 1,
)]
class IntegerItem extends FieldItemBase
{
    public static function propertyDefinitions(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public static function mainPropertyName(): string
    {
        return 'value';
    }

    public static function schema(): array
    {
        return [
            'value' => ['type' => 'int'],
        ];
    }

    public static function jsonSchema(): array
    {
        return ['type' => 'integer'];
    }
}

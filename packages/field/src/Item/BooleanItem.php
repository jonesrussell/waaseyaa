<?php

declare(strict_types=1);

namespace Aurora\Field\Item;

use Aurora\Field\Attribute\FieldType;
use Aurora\Field\FieldItemBase;

#[FieldType(
    id: 'boolean',
    label: 'Boolean',
    description: 'A field containing a boolean value.',
    category: 'general',
    defaultCardinality: 1,
)]
class BooleanItem extends FieldItemBase
{
    public static function propertyDefinitions(): array
    {
        return [
            'value' => 'boolean',
        ];
    }

    public static function mainPropertyName(): string
    {
        return 'value';
    }

    public static function schema(): array
    {
        return [
            'value' => ['type' => 'int', 'size' => 'tiny'],
        ];
    }

    public static function jsonSchema(): array
    {
        return ['type' => 'boolean'];
    }
}

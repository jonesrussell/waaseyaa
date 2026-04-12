<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Item;

use Waaseyaa\Field\Attribute\FieldType;
use Waaseyaa\Field\FieldItemBase;

#[FieldType(
    id: 'float',
    label: 'Float',
    description: 'A field containing a floating-point number.',
    category: 'general',
    defaultCardinality: 1,
)]
final class FloatItem extends FieldItemBase
{
    public static function propertyDefinitions(): array
    {
        return [
            'value' => 'float',
        ];
    }

    public static function mainPropertyName(): string
    {
        return 'value';
    }

    public static function schema(): array
    {
        return [
            'value' => ['type' => 'float'],
        ];
    }

    public static function jsonSchema(): array
    {
        return ['type' => 'number'];
    }
}

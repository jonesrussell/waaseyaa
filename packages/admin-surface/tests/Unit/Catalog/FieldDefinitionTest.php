<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AdminSurface\Catalog\FieldDefinition;

#[CoversClass(FieldDefinition::class)]
final class FieldDefinitionTest extends TestCase
{
    #[Test]
    public function minimalFieldToArray(): void
    {
        $field = new FieldDefinition('title', 'Title', 'string');

        $array = $field->toArray();

        self::assertSame('title', $array['name']);
        self::assertSame('Title', $array['label']);
        self::assertSame('string', $array['type']);
        self::assertArrayNotHasKey('widget', $array);
        self::assertArrayNotHasKey('weight', $array);
        self::assertArrayNotHasKey('required', $array);
    }

    #[Test]
    public function fullyConfiguredField(): void
    {
        $field = new FieldDefinition('body', 'Body', 'string');
        $field->widget('richtext')
            ->weight(10)
            ->required()
            ->accessRestricted()
            ->options(['maxLength' => 5000]);

        $array = $field->toArray();

        self::assertSame('richtext', $array['widget']);
        self::assertSame(10, $array['weight']);
        self::assertTrue($array['required']);
        self::assertTrue($array['accessRestricted']);
        self::assertSame(['maxLength' => 5000], $array['options']);
    }

    #[Test]
    public function readOnlyField(): void
    {
        $field = new FieldDefinition('uuid', 'UUID', 'string');
        $field->readOnly();

        $array = $field->toArray();

        self::assertTrue($array['readOnly']);
    }
}

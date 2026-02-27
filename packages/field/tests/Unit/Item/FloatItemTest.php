<?php

declare(strict_types=1);

namespace Aurora\Field\Tests\Unit\Item;

use Aurora\Field\Item\FloatItem;
use Aurora\Plugin\Definition\PluginDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Field\Item\FloatItem
 */
class FloatItemTest extends TestCase
{
    private function createItem(array $values = []): FloatItem
    {
        $pluginDefinition = new PluginDefinition(
            id: 'float',
            label: 'Float',
            class: FloatItem::class,
        );

        $configuration = [];
        if ($values !== []) {
            $configuration['values'] = $values;
        }

        return new FloatItem('float', $pluginDefinition, $configuration);
    }

    public function testPropertyDefinitions(): void
    {
        $this->assertSame(['value' => 'float'], FloatItem::propertyDefinitions());
    }

    public function testMainPropertyName(): void
    {
        $this->assertSame('value', FloatItem::mainPropertyName());
    }

    public function testSchema(): void
    {
        $this->assertSame(
            ['value' => ['type' => 'float']],
            FloatItem::schema(),
        );
    }

    public function testJsonSchema(): void
    {
        $this->assertSame(['type' => 'number'], FloatItem::jsonSchema());
    }

    public function testGetValue(): void
    {
        $item = $this->createItem(['value' => 3.14]);

        $this->assertSame(3.14, $item->getValue());
    }

    public function testIsEmpty(): void
    {
        $item = $this->createItem();

        $this->assertTrue($item->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $item = $this->createItem(['value' => 0.0]);

        // 0.0 is not null or empty string
        $this->assertFalse($item->isEmpty());
    }

    public function testToArray(): void
    {
        $item = $this->createItem(['value' => 2.718]);

        $this->assertSame(['value' => 2.718], $item->toArray());
    }

    public function testGetString(): void
    {
        $item = $this->createItem(['value' => 3.14]);

        $this->assertSame('3.14', $item->getString());
    }
}

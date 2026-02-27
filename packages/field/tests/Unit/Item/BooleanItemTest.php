<?php

declare(strict_types=1);

namespace Aurora\Field\Tests\Unit\Item;

use Aurora\Field\Item\BooleanItem;
use Aurora\Plugin\Definition\PluginDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Field\Item\BooleanItem
 */
class BooleanItemTest extends TestCase
{
    private function createItem(array $values = []): BooleanItem
    {
        $pluginDefinition = new PluginDefinition(
            id: 'boolean',
            label: 'Boolean',
            class: BooleanItem::class,
        );

        $configuration = [];
        if ($values !== []) {
            $configuration['values'] = $values;
        }

        return new BooleanItem('boolean', $pluginDefinition, $configuration);
    }

    public function testPropertyDefinitions(): void
    {
        $this->assertSame(['value' => 'boolean'], BooleanItem::propertyDefinitions());
    }

    public function testMainPropertyName(): void
    {
        $this->assertSame('value', BooleanItem::mainPropertyName());
    }

    public function testSchema(): void
    {
        $this->assertSame(
            ['value' => ['type' => 'int', 'size' => 'tiny']],
            BooleanItem::schema(),
        );
    }

    public function testJsonSchema(): void
    {
        $this->assertSame(['type' => 'boolean'], BooleanItem::jsonSchema());
    }

    public function testGetValueTrue(): void
    {
        $item = $this->createItem(['value' => true]);

        $this->assertTrue($item->getValue());
    }

    public function testGetValueFalse(): void
    {
        $item = $this->createItem(['value' => false]);

        // false is not null or empty string, but let's check isEmpty separately
        $this->assertFalse($item->getValue());
    }

    public function testIsEmpty(): void
    {
        $item = $this->createItem();

        $this->assertTrue($item->isEmpty());
    }

    public function testIsNotEmptyWithTrue(): void
    {
        $item = $this->createItem(['value' => true]);

        $this->assertFalse($item->isEmpty());
    }

    public function testToArray(): void
    {
        $item = $this->createItem(['value' => true]);

        $this->assertSame(['value' => true], $item->toArray());
    }

    public function testGetString(): void
    {
        $item = $this->createItem(['value' => true]);

        $this->assertSame('1', $item->getString());
    }
}

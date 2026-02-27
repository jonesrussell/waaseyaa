<?php

declare(strict_types=1);

namespace Aurora\Field\Tests\Unit\Item;

use Aurora\Field\Item\IntegerItem;
use Aurora\Plugin\Definition\PluginDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Field\Item\IntegerItem
 */
class IntegerItemTest extends TestCase
{
    private function createItem(array $values = []): IntegerItem
    {
        $pluginDefinition = new PluginDefinition(
            id: 'integer',
            label: 'Integer',
            class: IntegerItem::class,
        );

        $configuration = [];
        if ($values !== []) {
            $configuration['values'] = $values;
        }

        return new IntegerItem('integer', $pluginDefinition, $configuration);
    }

    public function testPropertyDefinitions(): void
    {
        $this->assertSame(['value' => 'integer'], IntegerItem::propertyDefinitions());
    }

    public function testMainPropertyName(): void
    {
        $this->assertSame('value', IntegerItem::mainPropertyName());
    }

    public function testSchema(): void
    {
        $this->assertSame(
            ['value' => ['type' => 'int']],
            IntegerItem::schema(),
        );
    }

    public function testJsonSchema(): void
    {
        $this->assertSame(['type' => 'integer'], IntegerItem::jsonSchema());
    }

    public function testGetValue(): void
    {
        $item = $this->createItem(['value' => 42]);

        $this->assertSame(42, $item->getValue());
    }

    public function testIsEmpty(): void
    {
        $item = $this->createItem();

        $this->assertTrue($item->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $item = $this->createItem(['value' => 0]);

        // 0 is not null/empty-string, so it counts as non-empty for integer
        $this->assertFalse($item->isEmpty());
    }

    public function testToArray(): void
    {
        $item = $this->createItem(['value' => 99]);

        $this->assertSame(['value' => 99], $item->toArray());
    }

    public function testGetString(): void
    {
        $item = $this->createItem(['value' => 42]);

        $this->assertSame('42', $item->getString());
    }
}

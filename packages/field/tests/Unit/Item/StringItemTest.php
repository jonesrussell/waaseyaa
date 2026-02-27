<?php

declare(strict_types=1);

namespace Aurora\Field\Tests\Unit\Item;

use Aurora\Field\Item\StringItem;
use Aurora\Plugin\Definition\PluginDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Field\Item\StringItem
 */
class StringItemTest extends TestCase
{
    private function createItem(array $values = []): StringItem
    {
        $pluginDefinition = new PluginDefinition(
            id: 'string',
            label: 'String',
            class: StringItem::class,
        );

        $configuration = [];
        if ($values !== []) {
            $configuration['values'] = $values;
        }

        return new StringItem('string', $pluginDefinition, $configuration);
    }

    public function testPropertyDefinitions(): void
    {
        $this->assertSame(['value' => 'string'], StringItem::propertyDefinitions());
    }

    public function testMainPropertyName(): void
    {
        $this->assertSame('value', StringItem::mainPropertyName());
    }

    public function testSchema(): void
    {
        $this->assertSame(
            ['value' => ['type' => 'varchar', 'length' => 255]],
            StringItem::schema(),
        );
    }

    public function testJsonSchema(): void
    {
        $this->assertSame(
            ['type' => 'string', 'maxLength' => 255],
            StringItem::jsonSchema(),
        );
    }

    public function testIsEmptyWithNull(): void
    {
        $item = $this->createItem();

        $this->assertTrue($item->isEmpty());
    }

    public function testIsEmptyWithEmptyString(): void
    {
        $item = $this->createItem(['value' => '']);

        $this->assertTrue($item->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $item = $this->createItem(['value' => 'Hello']);

        $this->assertFalse($item->isEmpty());
    }

    public function testGetValue(): void
    {
        $item = $this->createItem(['value' => 'Test string']);

        $this->assertSame('Test string', $item->getValue());
    }

    public function testSetValue(): void
    {
        $item = $this->createItem();

        $item->setValue('New value');

        $this->assertSame('New value', $item->getValue());
    }

    public function testToArray(): void
    {
        $item = $this->createItem(['value' => 'Array test']);

        $this->assertSame(['value' => 'Array test'], $item->toArray());
    }

    public function testGetString(): void
    {
        $item = $this->createItem(['value' => 'String test']);

        $this->assertSame('String test', $item->getString());
    }
}

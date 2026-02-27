<?php

declare(strict_types=1);

namespace Aurora\Field\Tests\Unit;

use Aurora\Field\FieldDefinition;
use Aurora\Field\FieldItemList;
use Aurora\Field\FieldItemListInterface;
use Aurora\Field\Item\StringItem;
use Aurora\Field\Item\TextItem;
use Aurora\Plugin\Definition\PluginDefinition;
use Aurora\TypedData\ListInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Field\FieldItemList
 */
class FieldItemListTest extends TestCase
{
    private function createFieldDefinition(string $name = 'title', string $type = 'string'): FieldDefinition
    {
        return new FieldDefinition(name: $name, type: $type);
    }

    private function createStringItem(string $value = ''): StringItem
    {
        $pluginDefinition = new PluginDefinition(
            id: 'string',
            label: 'String',
            class: StringItem::class,
        );

        $configuration = [];
        if ($value !== '') {
            $configuration['values'] = ['value' => $value];
        }

        return new StringItem('string', $pluginDefinition, $configuration);
    }

    public function testImplementsInterfaces(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->assertInstanceOf(FieldItemListInterface::class, $list);
        $this->assertInstanceOf(ListInterface::class, $list);
        $this->assertInstanceOf(\Countable::class, $list);
        $this->assertInstanceOf(\IteratorAggregate::class, $list);
    }

    public function testGetFieldDefinition(): void
    {
        $definition = $this->createFieldDefinition();
        $list = new FieldItemList($definition);

        $this->assertSame($definition, $list->getFieldDefinition());
    }

    public function testIsEmptyWhenNoItems(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->assertTrue($list->isEmpty());
    }

    public function testIsEmptyWhenAllItemsAreEmpty(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $list->appendItem($this->createStringItem());

        $this->assertTrue($list->isEmpty());
    }

    public function testIsNotEmptyWithNonEmptyItem(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $list->appendItem($this->createStringItem('Hello'));

        $this->assertFalse($list->isEmpty());
    }

    public function testAppendItem(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $item = $this->createStringItem('Test');

        $result = $list->appendItem($item);

        $this->assertSame($item, $result);
        $this->assertCount(1, $list);
    }

    public function testAppendItemThrowsForNonFieldItem(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->expectException(\InvalidArgumentException::class);
        $list->appendItem('not a field item');
    }

    public function testGetItem(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $item = $this->createStringItem('Test');
        $list->appendItem($item);

        $this->assertSame($item, $list->get(0));
    }

    public function testGetItemOutOfBounds(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->expectException(\OutOfBoundsException::class);
        $list->get(0);
    }

    public function testSetItemWithFieldItemInterface(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $item1 = $this->createStringItem('First');
        $item2 = $this->createStringItem('Second');
        $list->appendItem($item1);

        $list->set(0, $item2);

        $this->assertSame($item2, $list->get(0));
    }

    public function testSetItemWithValueUpdatesExisting(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $item = $this->createStringItem('Original');
        $list->appendItem($item);

        $list->set(0, 'Updated');

        $this->assertSame('Updated', $list->get(0)->getValue());
    }

    public function testSetItemOutOfBoundsThrows(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->expectException(\OutOfBoundsException::class);
        $list->set(0, 'value');
    }

    public function testFirst(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $item1 = $this->createStringItem('First');
        $item2 = $this->createStringItem('Second');
        $list->appendItem($item1);
        $list->appendItem($item2);

        $this->assertSame($item1, $list->first());
    }

    public function testFirstReturnsNullWhenEmpty(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->assertNull($list->first());
    }

    public function testRemoveItem(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $item1 = $this->createStringItem('First');
        $item2 = $this->createStringItem('Second');
        $list->appendItem($item1);
        $list->appendItem($item2);

        $list->removeItem(0);

        $this->assertCount(1, $list);
        $this->assertSame($item2, $list->get(0));
    }

    public function testRemoveItemOutOfBoundsThrows(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->expectException(\OutOfBoundsException::class);
        $list->removeItem(0);
    }

    public function testCount(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->assertCount(0, $list);

        $list->appendItem($this->createStringItem('A'));
        $this->assertCount(1, $list);

        $list->appendItem($this->createStringItem('B'));
        $this->assertCount(2, $list);
    }

    public function testGetMagicPropertyProxiesToFirstItem(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $list->appendItem($this->createStringItem('Hello'));

        $this->assertSame('Hello', $list->value);
    }

    public function testGetMagicPropertyReturnsNullWhenEmpty(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->assertNull($list->value);
    }

    public function testGetValue(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $list->appendItem($this->createStringItem('One'));
        $list->appendItem($this->createStringItem('Two'));

        $values = $list->getValue();

        $this->assertSame([
            ['value' => 'One'],
            ['value' => 'Two'],
        ], $values);
    }

    public function testGetString(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $list->appendItem($this->createStringItem('One'));
        $list->appendItem($this->createStringItem('Two'));

        $this->assertSame('One, Two', $list->getString());
    }

    public function testGetStringEmpty(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $this->assertSame('', $list->getString());
    }

    public function testValidate(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());

        $violations = $list->validate();

        $this->assertCount(0, $violations);
    }

    public function testIterator(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $item1 = $this->createStringItem('A');
        $item2 = $this->createStringItem('B');
        $list->appendItem($item1);
        $list->appendItem($item2);

        $items = [];
        foreach ($list as $item) {
            $items[] = $item->getValue();
        }

        $this->assertSame(['A', 'B'], $items);
    }

    public function testGetDataDefinition(): void
    {
        $definition = $this->createFieldDefinition();
        $list = new FieldItemList($definition);

        $this->assertSame($definition, $list->getDataDefinition());
    }

    public function testSetValueUpdatesExistingItems(): void
    {
        $list = new FieldItemList($this->createFieldDefinition());
        $list->appendItem($this->createStringItem('Original'));

        $list->setValue(['Updated']);

        $this->assertSame('Updated', $list->get(0)->getValue());
    }
}

<?php

declare(strict_types=1);

namespace Aurora\TypedData\Tests\Unit\Type;

use Aurora\TypedData\DataDefinition;
use Aurora\TypedData\ListInterface;
use Aurora\TypedData\Type\ListData;
use Aurora\TypedData\Type\StringData;
use Aurora\TypedData\TypedDataInterface;
use Aurora\TypedData\TypedDataManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListData::class)]
final class ListDataTest extends TestCase
{
    private DataDefinition $definition;
    private TypedDataManager $manager;

    protected function setUp(): void
    {
        $this->definition = new DataDefinition(dataType: 'list', isList: true);
        $this->manager = new TypedDataManager();
    }

    public function testImplementsInterfaces(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $this->assertInstanceOf(ListInterface::class, $list);
        $this->assertInstanceOf(TypedDataInterface::class, $list);
        $this->assertInstanceOf(\Countable::class, $list);
        $this->assertInstanceOf(\IteratorAggregate::class, $list);
        $this->assertInstanceOf(\Traversable::class, $list);
    }

    public function testEmptyListByDefault(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $this->assertTrue($list->isEmpty());
        $this->assertCount(0, $list);
        $this->assertSame([], $list->getValue());
    }

    public function testAppendItem(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $item = $list->appendItem('hello');

        $this->assertInstanceOf(TypedDataInterface::class, $item);
        $this->assertSame('hello', $item->getValue());
        $this->assertCount(1, $list);
        $this->assertFalse($list->isEmpty());
    }

    public function testAppendMultipleItems(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $list->appendItem('first');
        $list->appendItem('second');
        $list->appendItem('third');

        $this->assertCount(3, $list);
        $this->assertSame(['first', 'second', 'third'], $list->getValue());
    }

    public function testAppendTypedDataItem(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $stringDef = new DataDefinition(dataType: 'string');
        $item = new StringData($stringDef, 'typed');

        $returned = $list->appendItem($item);

        $this->assertSame($item, $returned);
        $this->assertCount(1, $list);
        $this->assertSame('typed', $list->get(0)->getValue());
    }

    public function testGetByIndex(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('hello');
        $list->appendItem('world');

        $this->assertSame('hello', $list->get(0)->getValue());
        $this->assertSame('world', $list->get(1)->getValue());
    }

    public function testGetOutOfRangeThrowsException(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $this->expectException(\OutOfRangeException::class);
        $list->get(0);
    }

    public function testSetByIndex(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('original');

        $list->set(0, 'updated');

        $this->assertSame('updated', $list->get(0)->getValue());
    }

    public function testSetOutOfRangeThrowsException(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $this->expectException(\OutOfRangeException::class);
        $list->set(5, 'value');
    }

    public function testSetWithTypedDataInstance(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('original');

        $stringDef = new DataDefinition(dataType: 'string');
        $newItem = new StringData($stringDef, 'replacement');

        $list->set(0, $newItem);

        $this->assertSame($newItem, $list->get(0));
        $this->assertSame('replacement', $list->get(0)->getValue());
    }

    public function testFirst(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('first');
        $list->appendItem('second');

        $first = $list->first();

        $this->assertNotNull($first);
        $this->assertSame('first', $first->getValue());
    }

    public function testFirstOnEmptyListReturnsNull(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $this->assertNull($list->first());
    }

    public function testRemoveItem(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('a');
        $list->appendItem('b');
        $list->appendItem('c');

        $list->removeItem(1);

        $this->assertCount(2, $list);
        $this->assertSame('a', $list->get(0)->getValue());
        $this->assertSame('c', $list->get(1)->getValue());
    }

    public function testRemoveItemReindexes(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('a');
        $list->appendItem('b');
        $list->appendItem('c');

        $list->removeItem(0);

        $this->assertCount(2, $list);
        // After removal, items are reindexed.
        $this->assertSame('b', $list->get(0)->getValue());
        $this->assertSame('c', $list->get(1)->getValue());
    }

    public function testRemoveItemOutOfRangeThrowsException(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $this->expectException(\OutOfRangeException::class);
        $list->removeItem(0);
    }

    public function testSetValue(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $list->setValue(['a', 'b', 'c']);

        $this->assertCount(3, $list);
        $this->assertSame(['a', 'b', 'c'], $list->getValue());
    }

    public function testSetValueReplacesExisting(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('old');

        $list->setValue(['new1', 'new2']);

        $this->assertCount(2, $list);
        $this->assertSame(['new1', 'new2'], $list->getValue());
    }

    public function testGetString(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('hello');
        $list->appendItem('world');

        $this->assertSame('hello, world', $list->getString());
    }

    public function testGetStringEmpty(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $this->assertSame('', $list->getString());
    }

    public function testGetDataDefinition(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $this->assertSame($this->definition, $list->getDataDefinition());
    }

    public function testIterable(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('a');
        $list->appendItem('b');

        $values = [];
        foreach ($list as $item) {
            $values[] = $item->getValue();
        }

        $this->assertSame(['a', 'b'], $values);
    }

    public function testValidateAggregatesItemViolations(): void
    {
        $list = new ListData($this->definition, $this->manager);
        $list->appendItem('valid');

        $violations = $list->validate();

        $this->assertCount(0, $violations);
    }

    public function testAppendItemWithNullValue(): void
    {
        $list = new ListData($this->definition, $this->manager);

        $item = $list->appendItem(null);

        $this->assertInstanceOf(TypedDataInterface::class, $item);
        $this->assertNull($item->getValue());
        $this->assertCount(1, $list);
    }

    public function testWithIntegerItemType(): void
    {
        $list = new ListData($this->definition, $this->manager, 'integer');

        $list->appendItem(42);
        $list->appendItem(99);

        $this->assertSame([42, 99], $list->getValue());
    }
}

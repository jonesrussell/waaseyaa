<?php

declare(strict_types=1);

namespace Aurora\Field\Tests\Unit;

use Aurora\Field\FieldDefinition;
use Aurora\Field\FieldItemBase;
use Aurora\Field\FieldItemInterface;
use Aurora\Field\FieldTypeInterface;
use Aurora\Field\Item\StringItem;
use Aurora\Field\Item\TextItem;
use Aurora\Field\PropertyValue;
use Aurora\Plugin\Definition\PluginDefinition;
use Aurora\TypedData\ComplexDataInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Field\FieldItemBase
 */
class FieldItemBaseTest extends TestCase
{
    private function createStringItem(array $values = [], ?FieldDefinition $fieldDefinition = null): StringItem
    {
        $pluginDefinition = new PluginDefinition(
            id: 'string',
            label: 'String',
            class: StringItem::class,
        );

        $configuration = [];
        if ($fieldDefinition !== null) {
            $configuration['field_definition'] = $fieldDefinition;
        }
        if ($values !== []) {
            $configuration['values'] = $values;
        }

        return new StringItem('string', $pluginDefinition, $configuration);
    }

    public function testImplementsInterfaces(): void
    {
        $item = $this->createStringItem();

        $this->assertInstanceOf(FieldItemInterface::class, $item);
        $this->assertInstanceOf(FieldTypeInterface::class, $item);
        $this->assertInstanceOf(ComplexDataInterface::class, $item);
        $this->assertInstanceOf(\IteratorAggregate::class, $item);
    }

    public function testIsEmptyWhenNoValue(): void
    {
        $item = $this->createStringItem();

        $this->assertTrue($item->isEmpty());
    }

    public function testIsEmptyWithEmptyString(): void
    {
        $item = $this->createStringItem(['value' => '']);

        $this->assertTrue($item->isEmpty());
    }

    public function testIsNotEmptyWithValue(): void
    {
        $item = $this->createStringItem(['value' => 'Hello']);

        $this->assertFalse($item->isEmpty());
    }

    public function testGetProperty(): void
    {
        $item = $this->createStringItem(['value' => 'Test']);

        $property = $item->get('value');

        $this->assertInstanceOf(PropertyValue::class, $property);
        $this->assertSame('Test', $property->getValue());
    }

    public function testGetNonExistentPropertyThrowsException(): void
    {
        $item = $this->createStringItem();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'nonexistent' does not exist.");
        $item->get('nonexistent');
    }

    public function testSetProperty(): void
    {
        $item = $this->createStringItem();

        $result = $item->set('value', 'New value');

        $this->assertSame($item, $result);
        $this->assertSame('New value', $item->get('value')->getValue());
    }

    public function testSetNonExistentPropertyThrowsException(): void
    {
        $item = $this->createStringItem();

        $this->expectException(\InvalidArgumentException::class);
        $item->set('nonexistent', 'value');
    }

    public function testGetProperties(): void
    {
        $item = $this->createStringItem(['value' => 'Test']);

        $properties = $item->getProperties();

        $this->assertArrayHasKey('value', $properties);
        $this->assertInstanceOf(PropertyValue::class, $properties['value']);
        $this->assertSame('Test', $properties['value']->getValue());
    }

    public function testToArray(): void
    {
        $item = $this->createStringItem(['value' => 'Hello world']);

        $array = $item->toArray();

        $this->assertSame(['value' => 'Hello world'], $array);
    }

    public function testToArrayWithNullProperties(): void
    {
        $item = $this->createStringItem();

        $array = $item->toArray();

        $this->assertSame(['value' => null], $array);
    }

    public function testToArrayWithMultipleProperties(): void
    {
        $pluginDefinition = new PluginDefinition(
            id: 'text',
            label: 'Text',
            class: TextItem::class,
        );

        $textItem = new TextItem('text', $pluginDefinition, [
            'values' => ['value' => 'Hello', 'format' => 'basic_html'],
        ]);

        $array = $textItem->toArray();

        $this->assertSame(['value' => 'Hello', 'format' => 'basic_html'], $array);
    }

    public function testGetValue(): void
    {
        $item = $this->createStringItem(['value' => 'Main value']);

        $this->assertSame('Main value', $item->getValue());
    }

    public function testSetValueWithScalar(): void
    {
        $item = $this->createStringItem();

        $item->setValue('New value');

        $this->assertSame('New value', $item->getValue());
    }

    public function testSetValueWithArray(): void
    {
        $item = $this->createStringItem();

        $item->setValue(['value' => 'Array value']);

        $this->assertSame('Array value', $item->getValue());
    }

    public function testGetFieldDefinition(): void
    {
        $definition = new FieldDefinition(name: 'title', type: 'string');
        $item = $this->createStringItem(fieldDefinition: $definition);

        $this->assertSame($definition, $item->getFieldDefinition());
    }

    public function testGetFieldDefinitionAutoCreated(): void
    {
        $item = $this->createStringItem();

        $fieldDef = $item->getFieldDefinition();

        $this->assertSame('string', $fieldDef->getType());
    }

    public function testGetDataDefinition(): void
    {
        $definition = new FieldDefinition(name: 'title', type: 'string');
        $item = $this->createStringItem(fieldDefinition: $definition);

        $this->assertSame($definition, $item->getDataDefinition());
    }

    public function testValidate(): void
    {
        $item = $this->createStringItem();

        $violations = $item->validate();

        $this->assertCount(0, $violations);
    }

    public function testGetString(): void
    {
        $item = $this->createStringItem(['value' => 'Hello']);

        $this->assertSame('Hello', $item->getString());
    }

    public function testGetStringWhenNull(): void
    {
        $item = $this->createStringItem();

        $this->assertSame('', $item->getString());
    }

    public function testIteratorAggregate(): void
    {
        $item = $this->createStringItem(['value' => 'Test']);

        $iterated = [];
        foreach ($item as $name => $property) {
            $iterated[$name] = $property->getValue();
        }

        $this->assertSame(['value' => 'Test'], $iterated);
    }

    public function testDefaultSettings(): void
    {
        $this->assertSame([], StringItem::defaultSettings());
    }

    public function testDefaultValue(): void
    {
        $this->assertNull(StringItem::defaultValue());
    }
}

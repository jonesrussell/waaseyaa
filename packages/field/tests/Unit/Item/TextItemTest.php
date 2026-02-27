<?php

declare(strict_types=1);

namespace Aurora\Field\Tests\Unit\Item;

use Aurora\Field\Item\TextItem;
use Aurora\Plugin\Definition\PluginDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Field\Item\TextItem
 */
class TextItemTest extends TestCase
{
    private function createItem(array $values = []): TextItem
    {
        $pluginDefinition = new PluginDefinition(
            id: 'text',
            label: 'Text',
            class: TextItem::class,
        );

        $configuration = [];
        if ($values !== []) {
            $configuration['values'] = $values;
        }

        return new TextItem('text', $pluginDefinition, $configuration);
    }

    public function testPropertyDefinitions(): void
    {
        $expected = [
            'value' => 'string',
            'format' => 'string',
        ];

        $this->assertSame($expected, TextItem::propertyDefinitions());
    }

    public function testMainPropertyName(): void
    {
        $this->assertSame('value', TextItem::mainPropertyName());
    }

    public function testSchema(): void
    {
        $expected = [
            'value' => ['type' => 'text'],
            'format' => ['type' => 'varchar', 'length' => 255],
        ];

        $this->assertSame($expected, TextItem::schema());
    }

    public function testJsonSchema(): void
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'string'],
                'format' => ['type' => 'string'],
            ],
        ];

        $this->assertSame($expected, TextItem::jsonSchema());
    }

    public function testGetValue(): void
    {
        $item = $this->createItem(['value' => '<p>Hello</p>', 'format' => 'basic_html']);

        $this->assertSame('<p>Hello</p>', $item->getValue());
    }

    public function testGetFormat(): void
    {
        $item = $this->createItem(['value' => 'Text', 'format' => 'plain_text']);

        $this->assertSame('plain_text', $item->get('format')->getValue());
    }

    public function testIsEmpty(): void
    {
        $item = $this->createItem();

        $this->assertTrue($item->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $item = $this->createItem(['value' => 'Content']);

        $this->assertFalse($item->isEmpty());
    }

    public function testToArray(): void
    {
        $item = $this->createItem(['value' => 'Hello', 'format' => 'basic_html']);

        $this->assertSame(
            ['value' => 'Hello', 'format' => 'basic_html'],
            $item->toArray(),
        );
    }

    public function testToArrayWithPartialValues(): void
    {
        $item = $this->createItem(['value' => 'Hello']);

        $this->assertSame(
            ['value' => 'Hello', 'format' => null],
            $item->toArray(),
        );
    }

    public function testSetValueWithArray(): void
    {
        $item = $this->createItem();

        $item->setValue(['value' => 'Updated', 'format' => 'full_html']);

        $this->assertSame('Updated', $item->getValue());
        $this->assertSame('full_html', $item->get('format')->getValue());
    }
}

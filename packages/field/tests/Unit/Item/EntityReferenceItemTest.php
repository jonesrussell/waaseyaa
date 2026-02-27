<?php

declare(strict_types=1);

namespace Aurora\Field\Tests\Unit\Item;

use Aurora\Field\Item\EntityReferenceItem;
use Aurora\Plugin\Definition\PluginDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Field\Item\EntityReferenceItem
 */
class EntityReferenceItemTest extends TestCase
{
    private function createItem(array $values = []): EntityReferenceItem
    {
        $pluginDefinition = new PluginDefinition(
            id: 'entity_reference',
            label: 'Entity Reference',
            class: EntityReferenceItem::class,
        );

        $configuration = [];
        if ($values !== []) {
            $configuration['values'] = $values;
        }

        return new EntityReferenceItem('entity_reference', $pluginDefinition, $configuration);
    }

    public function testPropertyDefinitions(): void
    {
        $expected = [
            'target_id' => 'integer',
            'target_type' => 'string',
        ];

        $this->assertSame($expected, EntityReferenceItem::propertyDefinitions());
    }

    public function testMainPropertyName(): void
    {
        $this->assertSame('target_id', EntityReferenceItem::mainPropertyName());
    }

    public function testSchema(): void
    {
        $expected = [
            'target_id' => ['type' => 'int'],
            'target_type' => ['type' => 'varchar', 'length' => 255],
        ];

        $this->assertSame($expected, EntityReferenceItem::schema());
    }

    public function testJsonSchema(): void
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'target_id' => ['type' => 'integer'],
                'target_type' => ['type' => 'string'],
            ],
        ];

        $this->assertSame($expected, EntityReferenceItem::jsonSchema());
    }

    public function testGetValue(): void
    {
        $item = $this->createItem(['target_id' => 42, 'target_type' => 'node']);

        $this->assertSame(42, $item->getValue());
    }

    public function testGetTargetType(): void
    {
        $item = $this->createItem(['target_id' => 1, 'target_type' => 'user']);

        $this->assertSame('user', $item->get('target_type')->getValue());
    }

    public function testIsEmpty(): void
    {
        $item = $this->createItem();

        $this->assertTrue($item->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $item = $this->createItem(['target_id' => 1]);

        $this->assertFalse($item->isEmpty());
    }

    public function testToArray(): void
    {
        $item = $this->createItem(['target_id' => 5, 'target_type' => 'node']);

        $this->assertSame(
            ['target_id' => 5, 'target_type' => 'node'],
            $item->toArray(),
        );
    }

    public function testSetValueWithArray(): void
    {
        $item = $this->createItem();

        $item->setValue(['target_id' => 10, 'target_type' => 'taxonomy_term']);

        $this->assertSame(10, $item->getValue());
        $this->assertSame('taxonomy_term', $item->get('target_type')->getValue());
    }

    public function testGetString(): void
    {
        $item = $this->createItem(['target_id' => 42]);

        $this->assertSame('42', $item->getString());
    }
}

<?php

declare(strict_types=1);

namespace Aurora\TypedData\Tests\Unit;

use Aurora\TypedData\DataDefinition;
use Aurora\TypedData\DataDefinitionInterface;
use Aurora\TypedData\Type\BooleanData;
use Aurora\TypedData\Type\FloatData;
use Aurora\TypedData\Type\IntegerData;
use Aurora\TypedData\Type\ListData;
use Aurora\TypedData\Type\MapData;
use Aurora\TypedData\Type\StringData;
use Aurora\TypedData\TypedDataManager;
use Aurora\TypedData\TypedDataManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypedDataManager::class)]
final class TypedDataManagerTest extends TestCase
{
    private TypedDataManager $manager;

    protected function setUp(): void
    {
        $this->manager = new TypedDataManager();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(TypedDataManagerInterface::class, $this->manager);
    }

    public function testCreateDataDefinitionForString(): void
    {
        $definition = $this->manager->createDataDefinition('string');

        $this->assertInstanceOf(DataDefinitionInterface::class, $definition);
        $this->assertSame('string', $definition->getDataType());
    }

    public function testCreateDataDefinitionForInteger(): void
    {
        $definition = $this->manager->createDataDefinition('integer');

        $this->assertSame('integer', $definition->getDataType());
    }

    public function testCreateDataDefinitionForBoolean(): void
    {
        $definition = $this->manager->createDataDefinition('boolean');

        $this->assertSame('boolean', $definition->getDataType());
    }

    public function testCreateDataDefinitionForFloat(): void
    {
        $definition = $this->manager->createDataDefinition('float');

        $this->assertSame('float', $definition->getDataType());
    }

    public function testCreateDataDefinitionForList(): void
    {
        $definition = $this->manager->createDataDefinition('list');

        $this->assertSame('list', $definition->getDataType());
    }

    public function testCreateDataDefinitionForMap(): void
    {
        $definition = $this->manager->createDataDefinition('map');

        $this->assertSame('map', $definition->getDataType());
    }

    public function testCreateDataDefinitionForUnknownTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown data type "unknown".');
        $this->manager->createDataDefinition('unknown');
    }

    public function testCreateStringData(): void
    {
        $definition = new DataDefinition(dataType: 'string');

        $data = $this->manager->create($definition, 'hello');

        $this->assertInstanceOf(StringData::class, $data);
        $this->assertSame('hello', $data->getValue());
    }

    public function testCreateIntegerData(): void
    {
        $definition = new DataDefinition(dataType: 'integer');

        $data = $this->manager->create($definition, 42);

        $this->assertInstanceOf(IntegerData::class, $data);
        $this->assertSame(42, $data->getValue());
    }

    public function testCreateBooleanData(): void
    {
        $definition = new DataDefinition(dataType: 'boolean');

        $data = $this->manager->create($definition, true);

        $this->assertInstanceOf(BooleanData::class, $data);
        $this->assertTrue($data->getValue());
    }

    public function testCreateFloatData(): void
    {
        $definition = new DataDefinition(dataType: 'float');

        $data = $this->manager->create($definition, 3.14);

        $this->assertInstanceOf(FloatData::class, $data);
        $this->assertSame(3.14, $data->getValue());
    }

    public function testCreateListData(): void
    {
        $definition = new DataDefinition(dataType: 'list');

        $data = $this->manager->create($definition, ['a', 'b', 'c']);

        $this->assertInstanceOf(ListData::class, $data);
        $this->assertSame(['a', 'b', 'c'], $data->getValue());
    }

    public function testCreateMapData(): void
    {
        $definition = new DataDefinition(dataType: 'map');

        $data = $this->manager->create($definition, ['key' => 'value']);

        $this->assertInstanceOf(MapData::class, $data);
        $this->assertSame(['key' => 'value'], $data->getValue());
    }

    public function testCreateWithNullValue(): void
    {
        $definition = new DataDefinition(dataType: 'string');

        $data = $this->manager->create($definition);

        $this->assertInstanceOf(StringData::class, $data);
        $this->assertNull($data->getValue());
    }

    public function testCreateWithUnknownTypeThrows(): void
    {
        $definition = new DataDefinition(dataType: 'unknown');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown data type "unknown".');
        $this->manager->create($definition);
    }

    public function testCreateInstanceString(): void
    {
        $data = $this->manager->createInstance('string', ['value' => 'hello']);

        $this->assertInstanceOf(StringData::class, $data);
        $this->assertSame('hello', $data->getValue());
    }

    public function testCreateInstanceInteger(): void
    {
        $data = $this->manager->createInstance('integer', ['value' => 42]);

        $this->assertInstanceOf(IntegerData::class, $data);
        $this->assertSame(42, $data->getValue());
    }

    public function testCreateInstanceWithConfiguration(): void
    {
        $data = $this->manager->createInstance('string', [
            'label' => 'Title',
            'description' => 'The node title',
            'required' => true,
            'value' => 'My Page',
        ]);

        $this->assertSame('My Page', $data->getValue());
        $this->assertSame('Title', $data->getDataDefinition()->getLabel());
        $this->assertSame('The node title', $data->getDataDefinition()->getDescription());
        $this->assertTrue($data->getDataDefinition()->isRequired());
    }

    public function testCreateInstanceWithNoConfiguration(): void
    {
        $data = $this->manager->createInstance('string');

        $this->assertInstanceOf(StringData::class, $data);
        $this->assertNull($data->getValue());
    }

    public function testGetDefinitions(): void
    {
        $definitions = $this->manager->getDefinitions();

        $this->assertCount(6, $definitions);
        $this->assertArrayHasKey('string', $definitions);
        $this->assertArrayHasKey('integer', $definitions);
        $this->assertArrayHasKey('boolean', $definitions);
        $this->assertArrayHasKey('float', $definitions);
        $this->assertArrayHasKey('list', $definitions);
        $this->assertArrayHasKey('map', $definitions);

        foreach ($definitions as $type => $definition) {
            $this->assertInstanceOf(DataDefinitionInterface::class, $definition);
            $this->assertSame($type, $definition->getDataType());
        }
    }

    public function testCreateInstanceMap(): void
    {
        $data = $this->manager->createInstance('map', [
            'value' => ['name' => 'Alice', 'age' => 30],
        ]);

        $this->assertInstanceOf(MapData::class, $data);
        $this->assertSame(['name' => 'Alice', 'age' => 30], $data->getValue());
    }

    public function testCreateInstanceList(): void
    {
        $data = $this->manager->createInstance('list', [
            'value' => ['a', 'b', 'c'],
        ]);

        $this->assertInstanceOf(ListData::class, $data);
        $this->assertSame(['a', 'b', 'c'], $data->getValue());
    }
}

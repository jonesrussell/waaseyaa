<?php

declare(strict_types=1);

namespace Aurora\TypedData\Tests\Unit;

use Aurora\TypedData\DataDefinition;
use Aurora\TypedData\DataDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[CoversClass(DataDefinition::class)]
final class DataDefinitionTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $definition = new DataDefinition(dataType: 'string');

        $this->assertInstanceOf(DataDefinitionInterface::class, $definition);
    }

    public function testConstructWithDefaults(): void
    {
        $definition = new DataDefinition(dataType: 'string');

        $this->assertSame('string', $definition->getDataType());
        $this->assertSame('', $definition->getLabel());
        $this->assertSame('', $definition->getDescription());
        $this->assertFalse($definition->isRequired());
        $this->assertFalse($definition->isReadOnly());
        $this->assertFalse($definition->isList());
        $this->assertSame([], $definition->getConstraints());
    }

    public function testConstructWithAllProperties(): void
    {
        $constraints = [new NotBlank(), new Length(max: 255)];

        $definition = new DataDefinition(
            dataType: 'string',
            label: 'Title',
            description: 'The content title',
            required: true,
            readOnly: false,
            isList: false,
            constraints: $constraints,
        );

        $this->assertSame('string', $definition->getDataType());
        $this->assertSame('Title', $definition->getLabel());
        $this->assertSame('The content title', $definition->getDescription());
        $this->assertTrue($definition->isRequired());
        $this->assertFalse($definition->isReadOnly());
        $this->assertFalse($definition->isList());
        $this->assertCount(2, $definition->getConstraints());
        $this->assertSame($constraints, $definition->getConstraints());
    }

    public function testReadOnlyDefinition(): void
    {
        $definition = new DataDefinition(
            dataType: 'integer',
            readOnly: true,
        );

        $this->assertTrue($definition->isReadOnly());
    }

    public function testListDefinition(): void
    {
        $definition = new DataDefinition(
            dataType: 'list',
            isList: true,
        );

        $this->assertTrue($definition->isList());
    }

    public function testDifferentDataTypes(): void
    {
        $types = ['string', 'integer', 'boolean', 'float', 'list', 'map'];

        foreach ($types as $type) {
            $definition = new DataDefinition(dataType: $type);
            $this->assertSame($type, $definition->getDataType());
        }
    }
}

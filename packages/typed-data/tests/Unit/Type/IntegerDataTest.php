<?php

declare(strict_types=1);

namespace Aurora\TypedData\Tests\Unit\Type;

use Aurora\TypedData\DataDefinition;
use Aurora\TypedData\PrimitiveInterface;
use Aurora\TypedData\Type\IntegerData;
use Aurora\TypedData\TypedDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

#[CoversClass(IntegerData::class)]
final class IntegerDataTest extends TestCase
{
    private DataDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new DataDefinition(dataType: 'integer');
    }

    public function testImplementsInterfaces(): void
    {
        $data = new IntegerData($this->definition);

        $this->assertInstanceOf(TypedDataInterface::class, $data);
        $this->assertInstanceOf(PrimitiveInterface::class, $data);
    }

    public function testDefaultValueIsNull(): void
    {
        $data = new IntegerData($this->definition);

        $this->assertNull($data->getValue());
    }

    public function testSetAndGetValue(): void
    {
        $data = new IntegerData($this->definition, 42);

        $this->assertSame(42, $data->getValue());
    }

    public function testSetValue(): void
    {
        $data = new IntegerData($this->definition);
        $data->setValue(99);

        $this->assertSame(99, $data->getValue());
    }

    public function testGetDataDefinition(): void
    {
        $data = new IntegerData($this->definition);

        $this->assertSame($this->definition, $data->getDataDefinition());
    }

    public function testGetString(): void
    {
        $data = new IntegerData($this->definition, 42);

        $this->assertSame('42', $data->getString());
    }

    public function testGetStringWithNull(): void
    {
        $data = new IntegerData($this->definition);

        $this->assertSame('', $data->getString());
    }

    public function testGetStringWithZero(): void
    {
        $data = new IntegerData($this->definition, 0);

        $this->assertSame('0', $data->getString());
    }

    public function testGetCastedValue(): void
    {
        $data = new IntegerData($this->definition, 42);

        $this->assertSame(42, $data->getCastedValue());
        $this->assertIsInt($data->getCastedValue());
    }

    public function testGetCastedValueWithNull(): void
    {
        $data = new IntegerData($this->definition);

        $this->assertNull($data->getCastedValue());
    }

    public function testGetCastedValueCastsStringToInt(): void
    {
        $data = new IntegerData($this->definition, '123');

        $this->assertSame(123, $data->getCastedValue());
        $this->assertIsInt($data->getCastedValue());
    }

    public function testGetCastedValueCastsFloatToInt(): void
    {
        $data = new IntegerData($this->definition, 3.14);

        $this->assertSame(3, $data->getCastedValue());
        $this->assertIsInt($data->getCastedValue());
    }

    public function testValidateWithNoConstraints(): void
    {
        $data = new IntegerData($this->definition, 42);

        $violations = $data->validate();

        $this->assertCount(0, $violations);
    }

    public function testValidateWithPassingConstraints(): void
    {
        $definition = new DataDefinition(
            dataType: 'integer',
            constraints: [new Range(min: 1, max: 100)],
        );
        $data = new IntegerData($definition, 50);

        $violations = $data->validate();

        $this->assertCount(0, $violations);
    }

    public function testValidateWithFailingConstraints(): void
    {
        $definition = new DataDefinition(
            dataType: 'integer',
            constraints: [new GreaterThan(100)],
        );
        $data = new IntegerData($definition, 50);

        $violations = $data->validate();

        $this->assertGreaterThan(0, count($violations));
    }

    public function testValidateWithNullAndNotNullConstraint(): void
    {
        $definition = new DataDefinition(
            dataType: 'integer',
            constraints: [new NotNull()],
        );
        $data = new IntegerData($definition);

        $violations = $data->validate();

        $this->assertGreaterThan(0, count($violations));
    }

    public function testNegativeInteger(): void
    {
        $data = new IntegerData($this->definition, -5);

        $this->assertSame(-5, $data->getValue());
        $this->assertSame(-5, $data->getCastedValue());
        $this->assertSame('-5', $data->getString());
    }
}

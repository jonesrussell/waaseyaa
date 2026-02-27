<?php

declare(strict_types=1);

namespace Aurora\TypedData\Tests\Unit\Type;

use Aurora\TypedData\DataDefinition;
use Aurora\TypedData\PrimitiveInterface;
use Aurora\TypedData\Type\FloatData;
use Aurora\TypedData\TypedDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

#[CoversClass(FloatData::class)]
final class FloatDataTest extends TestCase
{
    private DataDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new DataDefinition(dataType: 'float');
    }

    public function testImplementsInterfaces(): void
    {
        $data = new FloatData($this->definition);

        $this->assertInstanceOf(TypedDataInterface::class, $data);
        $this->assertInstanceOf(PrimitiveInterface::class, $data);
    }

    public function testDefaultValueIsNull(): void
    {
        $data = new FloatData($this->definition);

        $this->assertNull($data->getValue());
    }

    public function testSetAndGetValue(): void
    {
        $data = new FloatData($this->definition, 3.14);

        $this->assertSame(3.14, $data->getValue());
    }

    public function testSetValue(): void
    {
        $data = new FloatData($this->definition);
        $data->setValue(2.71);

        $this->assertSame(2.71, $data->getValue());
    }

    public function testGetDataDefinition(): void
    {
        $data = new FloatData($this->definition);

        $this->assertSame($this->definition, $data->getDataDefinition());
    }

    public function testGetString(): void
    {
        $data = new FloatData($this->definition, 3.14);

        $this->assertSame('3.14', $data->getString());
    }

    public function testGetStringWithNull(): void
    {
        $data = new FloatData($this->definition);

        $this->assertSame('', $data->getString());
    }

    public function testGetStringWithZero(): void
    {
        $data = new FloatData($this->definition, 0.0);

        $this->assertSame('0', $data->getString());
    }

    public function testGetStringWithWholeNumber(): void
    {
        $data = new FloatData($this->definition, 5.0);

        $this->assertSame('5', $data->getString());
    }

    public function testGetCastedValue(): void
    {
        $data = new FloatData($this->definition, 3.14);

        $this->assertSame(3.14, $data->getCastedValue());
        $this->assertIsFloat($data->getCastedValue());
    }

    public function testGetCastedValueWithNull(): void
    {
        $data = new FloatData($this->definition);

        $this->assertNull($data->getCastedValue());
    }

    public function testGetCastedValueCastsStringToFloat(): void
    {
        $data = new FloatData($this->definition, '3.14');

        $this->assertSame(3.14, $data->getCastedValue());
        $this->assertIsFloat($data->getCastedValue());
    }

    public function testGetCastedValueCastsIntToFloat(): void
    {
        $data = new FloatData($this->definition, 42);

        $this->assertSame(42.0, $data->getCastedValue());
        $this->assertIsFloat($data->getCastedValue());
    }

    public function testValidateWithNoConstraints(): void
    {
        $data = new FloatData($this->definition, 3.14);

        $violations = $data->validate();

        $this->assertCount(0, $violations);
    }

    public function testValidateWithPassingConstraints(): void
    {
        $definition = new DataDefinition(
            dataType: 'float',
            constraints: [new Range(min: 0.0, max: 10.0)],
        );
        $data = new FloatData($definition, 5.5);

        $violations = $data->validate();

        $this->assertCount(0, $violations);
    }

    public function testValidateWithFailingConstraints(): void
    {
        $definition = new DataDefinition(
            dataType: 'float',
            constraints: [new LessThan(1.0)],
        );
        $data = new FloatData($definition, 5.5);

        $violations = $data->validate();

        $this->assertGreaterThan(0, count($violations));
    }

    public function testValidateNullWithNotNullConstraint(): void
    {
        $definition = new DataDefinition(
            dataType: 'float',
            constraints: [new NotNull()],
        );
        $data = new FloatData($definition);

        $violations = $data->validate();

        $this->assertGreaterThan(0, count($violations));
    }

    public function testNegativeFloat(): void
    {
        $data = new FloatData($this->definition, -2.5);

        $this->assertSame(-2.5, $data->getValue());
        $this->assertSame(-2.5, $data->getCastedValue());
        $this->assertSame('-2.5', $data->getString());
    }
}

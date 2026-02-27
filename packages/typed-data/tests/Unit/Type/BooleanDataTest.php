<?php

declare(strict_types=1);

namespace Aurora\TypedData\Tests\Unit\Type;

use Aurora\TypedData\DataDefinition;
use Aurora\TypedData\PrimitiveInterface;
use Aurora\TypedData\Type\BooleanData;
use Aurora\TypedData\TypedDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;

#[CoversClass(BooleanData::class)]
final class BooleanDataTest extends TestCase
{
    private DataDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new DataDefinition(dataType: 'boolean');
    }

    public function testImplementsInterfaces(): void
    {
        $data = new BooleanData($this->definition);

        $this->assertInstanceOf(TypedDataInterface::class, $data);
        $this->assertInstanceOf(PrimitiveInterface::class, $data);
    }

    public function testDefaultValueIsNull(): void
    {
        $data = new BooleanData($this->definition);

        $this->assertNull($data->getValue());
    }

    public function testSetAndGetTrueValue(): void
    {
        $data = new BooleanData($this->definition, true);

        $this->assertTrue($data->getValue());
    }

    public function testSetAndGetFalseValue(): void
    {
        $data = new BooleanData($this->definition, false);

        $this->assertFalse($data->getValue());
    }

    public function testSetValue(): void
    {
        $data = new BooleanData($this->definition);
        $data->setValue(true);

        $this->assertTrue($data->getValue());
    }

    public function testGetDataDefinition(): void
    {
        $data = new BooleanData($this->definition);

        $this->assertSame($this->definition, $data->getDataDefinition());
    }

    public function testGetStringWithTrue(): void
    {
        $data = new BooleanData($this->definition, true);

        $this->assertSame('1', $data->getString());
    }

    public function testGetStringWithFalse(): void
    {
        $data = new BooleanData($this->definition, false);

        $this->assertSame('0', $data->getString());
    }

    public function testGetStringWithNull(): void
    {
        $data = new BooleanData($this->definition);

        $this->assertSame('', $data->getString());
    }

    public function testGetCastedValueTrue(): void
    {
        $data = new BooleanData($this->definition, true);

        $this->assertTrue($data->getCastedValue());
        $this->assertIsBool($data->getCastedValue());
    }

    public function testGetCastedValueFalse(): void
    {
        $data = new BooleanData($this->definition, false);

        $this->assertFalse($data->getCastedValue());
        $this->assertIsBool($data->getCastedValue());
    }

    public function testGetCastedValueWithNull(): void
    {
        $data = new BooleanData($this->definition);

        $this->assertNull($data->getCastedValue());
    }

    public function testGetCastedValueCastsTruthyToBool(): void
    {
        $data = new BooleanData($this->definition, 1);

        $this->assertTrue($data->getCastedValue());
        $this->assertIsBool($data->getCastedValue());
    }

    public function testGetCastedValueCastsFalsyToBool(): void
    {
        $data = new BooleanData($this->definition, 0);

        $this->assertFalse($data->getCastedValue());
        $this->assertIsBool($data->getCastedValue());
    }

    public function testValidateWithNoConstraints(): void
    {
        $data = new BooleanData($this->definition, true);

        $violations = $data->validate();

        $this->assertCount(0, $violations);
    }

    public function testValidateWithPassingConstraints(): void
    {
        $definition = new DataDefinition(
            dataType: 'boolean',
            constraints: [new IsTrue()],
        );
        $data = new BooleanData($definition, true);

        $violations = $data->validate();

        $this->assertCount(0, $violations);
    }

    public function testValidateWithFailingConstraints(): void
    {
        $definition = new DataDefinition(
            dataType: 'boolean',
            constraints: [new IsTrue()],
        );
        $data = new BooleanData($definition, false);

        $violations = $data->validate();

        $this->assertGreaterThan(0, count($violations));
    }

    public function testValidateNullWithNotNullConstraint(): void
    {
        $definition = new DataDefinition(
            dataType: 'boolean',
            constraints: [new NotNull()],
        );
        $data = new BooleanData($definition);

        $violations = $data->validate();

        $this->assertGreaterThan(0, count($violations));
    }
}

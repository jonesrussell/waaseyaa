<?php

declare(strict_types=1);

namespace Aurora\TypedData\Tests\Unit\Type;

use Aurora\TypedData\DataDefinition;
use Aurora\TypedData\PrimitiveInterface;
use Aurora\TypedData\Type\StringData;
use Aurora\TypedData\TypedDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[CoversClass(StringData::class)]
final class StringDataTest extends TestCase
{
    private DataDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new DataDefinition(dataType: 'string');
    }

    public function testImplementsInterfaces(): void
    {
        $data = new StringData($this->definition);

        $this->assertInstanceOf(TypedDataInterface::class, $data);
        $this->assertInstanceOf(PrimitiveInterface::class, $data);
    }

    public function testDefaultValueIsNull(): void
    {
        $data = new StringData($this->definition);

        $this->assertNull($data->getValue());
    }

    public function testSetAndGetValue(): void
    {
        $data = new StringData($this->definition, 'hello');

        $this->assertSame('hello', $data->getValue());
    }

    public function testSetValue(): void
    {
        $data = new StringData($this->definition);
        $data->setValue('world');

        $this->assertSame('world', $data->getValue());
    }

    public function testGetDataDefinition(): void
    {
        $data = new StringData($this->definition);

        $this->assertSame($this->definition, $data->getDataDefinition());
    }

    public function testGetString(): void
    {
        $data = new StringData($this->definition, 'hello');

        $this->assertSame('hello', $data->getString());
    }

    public function testGetStringWithNull(): void
    {
        $data = new StringData($this->definition);

        $this->assertSame('', $data->getString());
    }

    public function testGetStringWithNumericValue(): void
    {
        $data = new StringData($this->definition, 42);

        $this->assertSame('42', $data->getString());
    }

    public function testGetCastedValue(): void
    {
        $data = new StringData($this->definition, 'hello');

        $this->assertSame('hello', $data->getCastedValue());
        $this->assertIsString($data->getCastedValue());
    }

    public function testGetCastedValueWithNull(): void
    {
        $data = new StringData($this->definition);

        $this->assertNull($data->getCastedValue());
    }

    public function testGetCastedValueCastsToString(): void
    {
        $data = new StringData($this->definition, 123);

        $this->assertSame('123', $data->getCastedValue());
        $this->assertIsString($data->getCastedValue());
    }

    public function testValidateWithNoConstraints(): void
    {
        $data = new StringData($this->definition, 'hello');

        $violations = $data->validate();

        $this->assertCount(0, $violations);
    }

    public function testValidateWithPassingConstraints(): void
    {
        $definition = new DataDefinition(
            dataType: 'string',
            constraints: [new Length(min: 1, max: 255)],
        );
        $data = new StringData($definition, 'hello');

        $violations = $data->validate();

        $this->assertCount(0, $violations);
    }

    public function testValidateWithFailingConstraints(): void
    {
        $definition = new DataDefinition(
            dataType: 'string',
            constraints: [new NotBlank()],
        );
        $data = new StringData($definition, '');

        $violations = $data->validate();

        $this->assertGreaterThan(0, count($violations));
    }

    public function testValidateWithLengthConstraint(): void
    {
        $definition = new DataDefinition(
            dataType: 'string',
            constraints: [new Length(max: 5)],
        );
        $data = new StringData($definition, 'too long string');

        $violations = $data->validate();

        $this->assertGreaterThan(0, count($violations));
    }
}

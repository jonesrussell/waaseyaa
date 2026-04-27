<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Field\FieldStorage;

/**
 * @covers \Waaseyaa\Entity\Attribute\Field
 */
#[CoversClass(Field::class)]
final class FieldAttributeTest extends TestCase
{
    #[Test]
    public function it_constructs_with_default_values(): void
    {
        $field = new Field();

        self::assertNull($field->type);
        self::assertNull($field->required);
        self::assertNull($field->default);
        self::assertSame('', $field->label);
        self::assertSame('', $field->description);
        self::assertSame([], $field->settings);
        self::assertFalse($field->readOnly);
        self::assertFalse($field->translatable);
        self::assertFalse($field->revisionable);
        self::assertSame(FieldStorage::Column, $field->stored);
    }

    #[Test]
    public function it_exposes_explicit_constructor_arguments_via_public_readonly_properties(): void
    {
        $field = new Field(
            type: 'string',
            required: true,
            default: 'foo',
            label: 'Name',
            description: 'desc',
            settings: ['x' => 1],
            readOnly: true,
            translatable: true,
            revisionable: true,
            stored: FieldStorage::Data,
        );

        self::assertSame('string', $field->type);
        self::assertTrue($field->required);
        self::assertSame('foo', $field->default);
        self::assertSame('Name', $field->label);
        self::assertSame('desc', $field->description);
        self::assertSame(['x' => 1], $field->settings);
        self::assertTrue($field->readOnly);
        self::assertTrue($field->translatable);
        self::assertTrue($field->revisionable);
        self::assertSame(FieldStorage::Data, $field->stored);
    }

    #[Test]
    public function it_accepts_stored_data(): void
    {
        $field = new Field(stored: FieldStorage::Data);

        self::assertSame(FieldStorage::Data, $field->stored);
    }

    #[Test]
    public function attribute_targets_property_only(): void
    {
        $reflection = new \ReflectionClass(Field::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        /** @var \Attribute $instance */
        $instance = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_PROPERTY, $instance->flags);
    }

    #[Test]
    public function class_is_final_and_readonly(): void
    {
        $reflection = new \ReflectionClass(Field::class);

        self::assertTrue($reflection->isFinal(), 'Field must be final.');
        self::assertTrue($reflection->isReadOnly(), 'Field must be readonly.');
    }

    #[Test]
    public function attribute_can_be_read_via_reflection_when_applied_to_a_property(): void
    {
        $fixture = new class () {
            #[Field(type: 'string', label: 'Title')]
            public string $title = '';
        };

        $reflection = new \ReflectionProperty($fixture, 'title');
        $attributes = $reflection->getAttributes(Field::class);

        self::assertCount(1, $attributes);

        $field = $attributes[0]->newInstance();
        self::assertInstanceOf(Field::class, $field);
        self::assertSame('string', $field->type);
        self::assertSame('Title', $field->label);
    }
}

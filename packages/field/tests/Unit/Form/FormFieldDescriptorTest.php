<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Field\Form\FormFieldDescriptor;

#[CoversClass(FormFieldDescriptor::class)]
class FormFieldDescriptorTest extends TestCase
{
    #[Test]
    public function constructorAcceptsAllFields(): void
    {
        $descriptor = new FormFieldDescriptor(
            name: 'title',
            type: 'string',
            label: 'Title',
            group: 'basic',
            value: 'Hello World',
            readOnly: false,
            required: true,
            errors: ['Value is required.'],
        );

        $this->assertSame('title', $descriptor->name);
        $this->assertSame('string', $descriptor->type);
        $this->assertSame('Title', $descriptor->label);
        $this->assertSame('basic', $descriptor->group);
        $this->assertSame('Hello World', $descriptor->value);
        $this->assertFalse($descriptor->readOnly);
        $this->assertTrue($descriptor->required);
        $this->assertSame(['Value is required.'], $descriptor->errors);
    }

    #[Test]
    public function errorsDefaultsToEmptyArray(): void
    {
        $descriptor = new FormFieldDescriptor(
            name: 'body',
            type: 'text',
            label: 'Body',
            group: '',
            value: null,
            readOnly: false,
            required: false,
        );

        $this->assertSame([], $descriptor->errors);
    }

    #[Test]
    public function valueAcceptsMixedTypes(): void
    {
        $nullDescriptor = new FormFieldDescriptor(
            name: 'field',
            type: 'string',
            label: 'Field',
            group: '',
            value: null,
            readOnly: false,
            required: false,
        );
        $this->assertNull($nullDescriptor->value);

        $intDescriptor = new FormFieldDescriptor(
            name: 'count',
            type: 'integer',
            label: 'Count',
            group: '',
            value: 42,
            readOnly: false,
            required: false,
        );
        $this->assertSame(42, $intDescriptor->value);

        $objectValue = new \stdClass();
        $objectDescriptor = new FormFieldDescriptor(
            name: 'ref',
            type: 'entity_reference',
            label: 'Ref',
            group: '',
            value: $objectValue,
            readOnly: false,
            required: false,
        );
        $this->assertSame($objectValue, $objectDescriptor->value);
    }

    #[Test]
    public function propertiesAreImmutable(): void
    {
        $descriptor = new FormFieldDescriptor(
            name: 'title',
            type: 'string',
            label: 'Title',
            group: '',
            value: null,
            readOnly: false,
            required: false,
        );

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        $descriptor->name = 'other';
    }

    #[Test]
    public function readOnlyAndRequiredFlagsRespected(): void
    {
        $descriptor = new FormFieldDescriptor(
            name: 'uid',
            type: 'integer',
            label: 'User ID',
            group: 'meta',
            value: 1,
            readOnly: true,
            required: false,
        );

        $this->assertTrue($descriptor->readOnly);
        $this->assertFalse($descriptor->required);
    }

    #[Test]
    public function groupAndLabelPreserved(): void
    {
        $descriptor = new FormFieldDescriptor(
            name: 'status',
            type: 'boolean',
            label: 'Published',
            group: 'publishing',
            value: true,
            readOnly: false,
            required: true,
        );

        $this->assertSame('publishing', $descriptor->group);
        $this->assertSame('Published', $descriptor->label);
    }
}

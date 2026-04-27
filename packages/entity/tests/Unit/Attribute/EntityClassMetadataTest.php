<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Attribute\EntityClassMetadata;
use Waaseyaa\Field\FieldDefinition;

#[CoversClass(EntityClassMetadata::class)]
final class EntityClassMetadataTest extends TestCase
{
    #[Test]
    public function it_constructs_with_minimal_arguments(): void
    {
        $meta = new EntityClassMetadata(typeId: 'foo', keys: ['id' => 'id']);

        self::assertSame('foo', $meta->typeId);
        self::assertSame(['id' => 'id'], $meta->keys);
        self::assertSame('', $meta->label);
        self::assertSame('', $meta->description);
        self::assertSame([], $meta->fields);
    }

    #[Test]
    public function it_exposes_all_fields_when_fully_specified(): void
    {
        $title = new FieldDefinition(name: 'title', type: 'string', required: true);
        $meta = new EntityClassMetadata(
            typeId: 'thing',
            keys: ['id' => 'id', 'label' => 'title'],
            label: 'Thing',
            description: 'A thing.',
            fields: ['title' => $title],
        );

        self::assertSame('thing', $meta->typeId);
        self::assertSame(['id' => 'id', 'label' => 'title'], $meta->keys);
        self::assertSame('Thing', $meta->label);
        self::assertSame('A thing.', $meta->description);
        self::assertSame(['title' => $title], $meta->fields);
    }

    #[Test]
    public function it_supports_a_null_type_id(): void
    {
        $meta = new EntityClassMetadata(typeId: null, keys: []);

        self::assertNull($meta->typeId);
    }

    #[Test]
    public function class_is_final_and_readonly(): void
    {
        $reflection = new \ReflectionClass(EntityClassMetadata::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }
}

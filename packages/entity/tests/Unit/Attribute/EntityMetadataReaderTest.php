<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Attribute\EntityClassMetadata;
use Waaseyaa\Entity\Attribute\EntityMetadataReader;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\MetadataChildEntity;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\MetadataNoLabelEntity;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\MetadataNonEntity;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\MetadataParentEntity;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\MetadataSimpleEntity;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\MetadataStoredMixedEntity;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\MetadataTypedOverrideEntity;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldStorage;

// Trigger fixture file load (multi-class file is not autoloaded by FQN of secondary classes).
require_once __DIR__ . '/../../Fixtures/AttributeFirstEntities/MetadataTestFixtures.php';

#[CoversClass(EntityMetadataReader::class)]
final class EntityMetadataReaderTest extends TestCase
{
    protected function setUp(): void
    {
        EntityMetadataReader::clearCache();
    }

    protected function tearDown(): void
    {
        EntityMetadataReader::clearCache();
    }

    #[Test]
    public function for_class_returns_label_and_description_from_attribute(): void
    {
        $meta = EntityMetadataReader::forClass(MetadataSimpleEntity::class);

        self::assertSame('metadata_simple', $meta->typeId);
        self::assertSame('Simple', $meta->label);
        self::assertSame('A simple test entity.', $meta->description);
    }

    #[Test]
    public function for_class_defaults_label_and_description_to_empty_string(): void
    {
        $meta = EntityMetadataReader::forClass(MetadataNoLabelEntity::class);

        self::assertSame('metadata_no_label', $meta->typeId);
        self::assertSame('', $meta->label);
        self::assertSame('', $meta->description);
    }

    #[Test]
    public function resolve_fields_returns_empty_for_non_content_entity(): void
    {
        self::assertSame([], EntityMetadataReader::resolveFields(MetadataNonEntity::class));
    }

    #[Test]
    public function resolve_fields_returns_definitions_for_each_field_attribute(): void
    {
        $fields = EntityMetadataReader::resolveFields(MetadataSimpleEntity::class);

        self::assertSame(['title', 'count', 'active', 'createdAt'], array_keys($fields));

        $title = $fields['title'];
        self::assertInstanceOf(FieldDefinition::class, $title);
        self::assertSame('title', $title->getName());
        self::assertSame('string', $title->getType());
        self::assertSame('Title', $title->getLabel());
        self::assertSame('Entity title.', $title->getDescription());
        self::assertTrue($title->isRequired());
        self::assertFalse($title->isReadOnly());
        self::assertFalse($title->isTranslatable());

        $count = $fields['count'];
        self::assertSame('integer', $count->getType());
        self::assertFalse($count->isRequired(), 'Nullable property should yield non-required field.');

        $active = $fields['active'];
        self::assertSame('boolean', $active->getType());
        self::assertFalse($active->getDefaultValue());

        $createdAt = $fields['createdAt'];
        self::assertSame('datetime', $createdAt->getType());
        self::assertTrue($createdAt->isTranslatable());
        self::assertTrue($createdAt->isRevisionable());
        self::assertTrue($createdAt->isReadOnly());
    }

    #[Test]
    public function resolve_fields_supports_explicit_type_override(): void
    {
        $fields = EntityMetadataReader::resolveFields(MetadataTypedOverrideEntity::class);

        self::assertArrayHasKey('body', $fields);
        self::assertSame('text', $fields['body']->getType());
        self::assertSame('Body', $fields['body']->getLabel());
    }

    #[Test]
    public function resolve_fields_forwards_stored_into_field_definition(): void
    {
        $fields = EntityMetadataReader::resolveFields(MetadataStoredMixedEntity::class);

        self::assertArrayHasKey('title', $fields);
        self::assertArrayHasKey('status', $fields);

        self::assertSame(FieldStorage::Column, $fields['title']->getStored());
        self::assertSame(FieldStorage::Data, $fields['status']->getStored());
    }

    #[Test]
    public function resolve_fields_walks_hierarchy_with_child_overrides(): void
    {
        $parentFields = EntityMetadataReader::resolveFields(MetadataParentEntity::class);
        self::assertSame(['name', 'weight'], array_keys($parentFields));
        self::assertSame('Parent name', $parentFields['name']->getLabel());

        $childFields = EntityMetadataReader::resolveFields(MetadataChildEntity::class);

        // Parent fields appear first, then child-declared fields. Child-overridden
        // 'name' wins thanks to the bottom-up walk + reverse + last-write semantics.
        self::assertContains('name', array_keys($childFields));
        self::assertContains('weight', array_keys($childFields));
        self::assertContains('extra', array_keys($childFields));

        self::assertSame('Child name', $childFields['name']->getLabel());
        self::assertSame('Overridden in child.', $childFields['name']->getDescription());
    }

    #[Test]
    public function for_class_populates_fields_in_resulting_metadata(): void
    {
        $meta = EntityMetadataReader::forClass(MetadataSimpleEntity::class);

        self::assertInstanceOf(EntityClassMetadata::class, $meta);
        self::assertCount(4, $meta->fields);
        self::assertArrayHasKey('title', $meta->fields);
    }

    #[Test]
    public function for_class_caches_metadata_per_class(): void
    {
        $first = EntityMetadataReader::forClass(MetadataSimpleEntity::class);
        $second = EntityMetadataReader::forClass(MetadataSimpleEntity::class);

        self::assertSame($first, $second, 'Cached lookup must return the same instance.');
    }

    #[Test]
    public function clear_cache_for_class_drops_cached_entry(): void
    {
        $first = EntityMetadataReader::forClass(MetadataSimpleEntity::class);
        EntityMetadataReader::clearCacheForClass(MetadataSimpleEntity::class);
        $second = EntityMetadataReader::forClass(MetadataSimpleEntity::class);

        self::assertNotSame($first, $second);
        self::assertEquals($first->typeId, $second->typeId);
    }

    #[Test]
    public function for_class_returns_null_typeid_for_missing_class(): void
    {
        /** @var class-string $missing */
        $missing = 'Some\\Class\\That\\Does\\Not\\Exist';
        $meta = EntityMetadataReader::forClass($missing);

        self::assertNull($meta->typeId);
        self::assertSame([], $meta->fields);
    }
}

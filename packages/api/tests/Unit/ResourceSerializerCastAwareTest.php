<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Api\ResourceSerializer;
use Waaseyaa\Api\Tests\Fixtures\ApiSerializeTestEnum;
use Waaseyaa\Api\Tests\Fixtures\CastAwareSerializeTestEntity;
use Waaseyaa\Api\Tests\Fixtures\TestEntity;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(ResourceSerializer::class)]
final class ResourceSerializerCastAwareTest extends TestCase
{
    #[Test]
    public function serializeUsesGetSoBackedEnumCastExposesBackingValueInAttributes(): void
    {
        $manager = new EntityTypeManager(new EventDispatcher());
        $manager->registerEntityType(new EntityType(
            id: 'cast_article',
            label: 'Cast article',
            class: CastAwareSerializeTestEntity::class,
            keys: TestEntity::definitionKeys(),
            fieldDefinitions: [],
        ));

        $serializer = new ResourceSerializer($manager);
        $entity = new CastAwareSerializeTestEntity([
            'id' => 1,
            'uuid' => 'uuid-cast-enum',
            'title' => 'T',
            'type' => 'blog',
            'phase' => ApiSerializeTestEnum::Alpha->value,
        ], 'cast_article');

        $resource = $serializer->serialize($entity);

        $this->assertSame(ApiSerializeTestEnum::Alpha->value, $resource->attributes['phase']);
        $json = json_encode($resource->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"phase":"alpha"', $json);
    }

    #[Test]
    public function serializeFormatsDatetimeImmutableFromCastWhenFieldDefIsTimestamp(): void
    {
        $manager = new EntityTypeManager(new EventDispatcher());
        $manager->registerEntityType(new EntityType(
            id: 'cast_article',
            label: 'Cast article',
            class: CastAwareSerializeTestEntity::class,
            keys: TestEntity::definitionKeys(),
            fieldDefinitions: [
                'published_at' => ['type' => 'timestamp'],
            ],
        ));

        $serializer = new ResourceSerializer($manager);
        $unix = 1_709_510_400;
        $entity = new CastAwareSerializeTestEntity([
            'id' => 1,
            'uuid' => 'uuid-cast-dt',
            'title' => 'T',
            'type' => 'blog',
            'published_at' => $unix,
        ], 'cast_article');

        $resource = $serializer->serialize($entity);

        $this->assertIsString($resource->attributes['published_at']);
        $this->assertStringContainsString('2024-03-04', $resource->attributes['published_at']);
    }
}

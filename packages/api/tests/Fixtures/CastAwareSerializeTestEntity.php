<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Fixtures;

use Waaseyaa\Entity\Attribute\ContentEntityType;

/**
 * Article-like entity with {@see $casts} for API serialization tests (#1181 ST-7).
 */
#[ContentEntityType(id: 'cast_article')]
final class CastAwareSerializeTestEntity extends TestEntity
{
    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'phase' => ApiSerializeTestEnum::class,
        'published_at' => 'datetime_immutable',
    ];
}

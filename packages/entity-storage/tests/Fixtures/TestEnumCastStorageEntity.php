<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

/**
 * Content entity with enum cast for repository validation tests (#1181 ST-6).
 */
final class TestEnumCastStorageEntity extends TestStorageEntity
{
    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'flag' => CastPersistenceStringEnum::class,
    ];
}

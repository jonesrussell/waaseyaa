<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Validation\Fixture;

use Waaseyaa\Entity\Tests\Unit\Cast\Fixture\SampleStringEnum;
use Waaseyaa\Entity\Tests\Unit\TestEntity;

/**
 * Entity with a backed-enum cast for {@see EntityValidator} / validation pipeline tests.
 */
final class BackedEnumCastEntity extends TestEntity
{
    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'status' => SampleStringEnum::class,
    ];
}

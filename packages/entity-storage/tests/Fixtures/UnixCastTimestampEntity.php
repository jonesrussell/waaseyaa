<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

/**
 * created_at / updated_at persisted as Unix integers via casts (#1183).
 */
final class UnixCastTimestampEntity extends TestStorageEntity
{
    /** @var array<string, string|array<string, mixed>> */
    protected array $casts = [
        'created_at' => ['type' => 'datetime_immutable', 'storage' => 'unix'],
        'updated_at' => ['type' => 'datetime_immutable', 'storage' => 'unix'],
    ];
}

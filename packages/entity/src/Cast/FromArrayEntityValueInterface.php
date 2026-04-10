<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Cast;

/**
 * Contract for entity field value objects hydrated from persisted array/JSON (#1184).
 *
 * Storage shape matches the {@code array} cast: JSON string in the entity value bag after {@see set()},
 * PHP array or JSON string acceptable when loading. The framework does not enforce immutability;
 * implementations SHOULD use readonly properties for predictable cast behavior.
 */
interface FromArrayEntityValueInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\DateTime;

use DateTimeImmutable;

/**
 * Deterministic clock for tests and backdated writes (#1183).
 */
final class FixedEntityClock implements EntityClockInterface
{
    public function __construct(
        private readonly DateTimeImmutable $fixed,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->fixed;
    }
}

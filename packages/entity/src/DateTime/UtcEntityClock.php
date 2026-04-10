<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\DateTime;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Default wall clock: "now" in UTC (#1183).
 */
final class UtcEntityClock implements EntityClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

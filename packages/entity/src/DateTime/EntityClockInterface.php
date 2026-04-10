<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\DateTime;

use DateTimeImmutable;

/**
 * Injectable clock for timestamp population and tests (#1183).
 */
interface EntityClockInterface
{
    public function now(): DateTimeImmutable;
}

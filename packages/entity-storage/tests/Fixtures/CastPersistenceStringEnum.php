<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

/**
 * Backed enum for cast persistence integration tests (#1181 ST-4/ST-5).
 */
enum CastPersistenceStringEnum: string
{
    case On = 'on';
    case Off = 'off';
}

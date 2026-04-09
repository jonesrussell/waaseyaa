<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Fixtures;

/**
 * Backed enum for ResourceSerializer cast-aware serialization tests (#1181 ST-7).
 */
enum ApiSerializeTestEnum: string
{
    case Alpha = 'alpha';
    case Beta = 'beta';
}

<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Attribute;

/**
 * Opt-in: inject the raw matched route parameter bag as array.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class MapRoute {}

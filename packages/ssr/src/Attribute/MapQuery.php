<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Attribute;

/**
 * Opt-in: inject the request query parameter bag as array.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class MapQuery {}

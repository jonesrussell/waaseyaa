<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class FromRoute
{
    public function __construct(
        public string $name,
    ) {}
}

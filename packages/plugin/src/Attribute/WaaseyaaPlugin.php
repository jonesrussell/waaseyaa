<?php

declare(strict_types=1);

namespace Waaseyaa\Plugin\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class WaaseyaaPlugin
{
    public function __construct(
        public readonly string $id,
        public readonly string $label = '',
        public readonly string $description = '',
        public readonly string $package = '',
    ) {}
}

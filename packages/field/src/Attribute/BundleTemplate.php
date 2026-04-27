<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class BundleTemplate
{
    public function __construct(
        public string $entityType,
        public string $bundle,
    ) {}
}

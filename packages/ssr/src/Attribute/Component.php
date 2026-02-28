<?php

declare(strict_types=1);

namespace Aurora\SSR\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Component
{
    public function __construct(
        public readonly string $name,
        public readonly string $template,
    ) {}
}

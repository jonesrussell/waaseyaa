<?php

declare(strict_types=1);

namespace Aurora\SSR;

final readonly class ComponentMetadata
{
    public function __construct(
        public string $name,
        public string $template,
        public string $className,
    ) {}
}

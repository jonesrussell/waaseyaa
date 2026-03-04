<?php

declare(strict_types=1);

namespace Waaseyaa\SSR;

final readonly class ViewMode
{
    public function __construct(
        public string $name,
    ) {}

    public static function full(): self
    {
        return new self('full');
    }

    public static function teaser(): self
    {
        return new self('teaser');
    }

    public static function embed(): self
    {
        return new self('embed');
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

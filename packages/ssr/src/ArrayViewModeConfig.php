<?php

declare(strict_types=1);

namespace Waaseyaa\SSR;

use Waaseyaa\Field\ViewModeConfigInterface;

final readonly class ArrayViewModeConfig implements ViewModeConfigInterface
{
    /**
     * @param array<string, array<string, array<string, array{formatter?: string, settings?: array<string, mixed>, weight?: int}>>> $config
     */
    public function __construct(
        private array $config = [],
    ) {}

    public function getDisplay(string $entityTypeId, string $viewMode): array
    {
        return $this->config[$entityTypeId][$viewMode] ?? [];
    }
}

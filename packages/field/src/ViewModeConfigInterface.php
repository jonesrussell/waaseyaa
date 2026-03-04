<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

interface ViewModeConfigInterface
{
    /**
     * Returns per-field display configuration for entity type + view mode.
     *
     * @return array<string, array{formatter?: string, settings?: array<string, mixed>, weight?: int}>
     */
    public function getDisplay(string $entityTypeId, string $viewMode): array;
}

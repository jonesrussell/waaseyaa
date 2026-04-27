<?php

declare(strict_types=1);

namespace Waaseyaa\Path;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class PathServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PathAlias's type metadata (id, label, keys, fields) lives on the
        // PathAlias class via #[ContentEntityType], #[ContentEntityKeys],
        // and #[Field] attributes.
        $this->entityType(EntityType::fromClass(
            PathAlias::class,
            group: 'structure',
        ));
    }
}

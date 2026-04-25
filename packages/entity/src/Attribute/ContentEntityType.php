<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Attribute;

/**
 * Declares the machine entity type id for a content entity class (e.g. 'todo', 'node').
 * Used by SSR app-controller argument binding to map PHP types to storage.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ContentEntityType
{
    public function __construct(
        public string $id,
    ) {}
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Hydration;

/**
 * Metadata passed when reconstituting an entity from a storage row.
 *
 * Implementations of {@see HydratableFromStorageInterface} use this when
 * bootstrapping {@see \Waaseyaa\Entity\ContentEntityBase} (or config entities)
 * with the entity type id and key map from {@see \Waaseyaa\Entity\EntityTypeInterface}.
 */
final readonly class HydrationContext
{
    /**
     * @param array<string, string> $entityKeys Maps logical key (id, uuid, label, …) to storage field names.
     */
    public function __construct(
        public string $entityTypeId,
        public array $entityKeys = [],
    ) {}
}

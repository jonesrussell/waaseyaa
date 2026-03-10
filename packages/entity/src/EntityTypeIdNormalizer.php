<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

/**
 * Normalizes entity type IDs by stripping known prefixes (e.g. "core.note" → "note")
 * when the stripped form is a registered type.
 */
final class EntityTypeIdNormalizer
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function normalize(string $typeId): string
    {
        $typeId = trim($typeId);

        if (str_starts_with($typeId, 'core.')) {
            $stripped = substr($typeId, 5);
            if ($stripped !== '' && $this->entityTypeManager->hasDefinition($stripped)) {
                return $stripped;
            }
        }

        return $typeId;
    }
}

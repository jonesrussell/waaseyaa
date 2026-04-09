<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;

/**
 * Content entity that rehydrates only via {@see HydratableFromStorageInterface}.
 */
final class HydratableFromStorageTestEntity extends ContentEntityBase implements HydratableFromStorageInterface
{
    public function __construct(
        array $values = [],
        string $entityTypeId = 'hydratable_test_entity',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    public static function fromStorage(array $values, HydrationContext $context): static
    {
        $merged = $values;
        $merged['_rehydrated_via_storage'] = true;
        $merged['_context_type'] = $context->entityTypeId;

        return new self(
            values: $merged,
            entityTypeId: $context->entityTypeId,
            entityKeys: $context->entityKeys,
        );
    }
}

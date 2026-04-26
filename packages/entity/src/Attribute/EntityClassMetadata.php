<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Attribute;

/**
 * Resolved {@see ContentEntityType} and {@see ContentEntityKeys} for a class.
 *
 * @phpstan-type EntityKeyMap array<string, string>
 */
final readonly class EntityClassMetadata
{
    /**
     * @param EntityKeyMap $keys
     */
    public function __construct(
        public ?string $typeId,
        public array $keys,
    ) {}
}

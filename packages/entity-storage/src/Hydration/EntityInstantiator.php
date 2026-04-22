<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Hydration;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;

/**
 * Centralizes entity construction from storage-normalized value bags.
 *
 * Used by {@see \Waaseyaa\EntityStorage\EntityRepository} and
 * {@see \Waaseyaa\EntityStorage\SqlEntityStorage} so hydration behavior stays
 * consistent.
 *
 * @internal Not part of the semver public API; disposition in docs/public-surface-map.php.
 */
final class EntityInstantiator
{
    public function __construct(
        private readonly EntityTypeInterface $entityType,
    ) {}

    /**
     * @param class-string $class
     * @param array<string, mixed> $values
     */
    public function instantiate(string $class, array $values): EntityInterface
    {
        if (!is_a($class, EntityInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Entity class "%s" must implement %s.',
                $class,
                EntityInterface::class,
            ));
        }

        if (!is_subclass_of($class, HydratableFromStorageInterface::class)) {
            throw new \RuntimeException(sprintf(
                'Entity class "%s" must implement %s for storage hydration.',
                $class,
                HydratableFromStorageInterface::class,
            ));
        }

        $context = new HydrationContext(
            entityTypeId: $this->entityType->id(),
            entityKeys: $this->entityType->getKeys(),
        );

        return $class::fromStorage($values, $context);
    }
}

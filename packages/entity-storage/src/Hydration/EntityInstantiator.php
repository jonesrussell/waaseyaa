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

        if (is_subclass_of($class, HydratableFromStorageInterface::class)) {
            $context = new HydrationContext(
                entityTypeId: $this->entityType->id(),
                entityKeys: $this->entityType->getKeys(),
            );

            return $class::fromStorage($values, $context);
        }

        return $this->instantiateLegacy($class, $values);
    }

    /**
     * Reflection-based hydration for types that do not yet implement
     * {@see HydratableFromStorageInterface}. Scheduled for removal once all
     * content entities use `fromStorage()` (see docs/specs/entity-system.md,
     * "Breaking-change cutover (alpha → stable)").
     *
     * @param class-string<EntityInterface> $class
     * @param array<string, mixed> $values
     */
    private function instantiateLegacy(string $class, array $values): EntityInterface
    {
        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();
        $hasEntityTypeId = false;

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                if ($param->getName() === 'entityTypeId') {
                    $hasEntityTypeId = true;
                    break;
                }
            }
        }

        $keys = $this->entityType->getKeys();

        if ($hasEntityTypeId) {
            /** @var EntityInterface */
            return new $class(
                values: $values,
                entityTypeId: $this->entityType->id(),
                entityKeys: $keys,
            );
        }

        /** @var EntityInterface */
        return new $class(values: $values);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

interface EntityTypeManagerInterface
{
    public function getDefinition(string $entityTypeId): EntityTypeInterface;

    /** @throws \DomainException If the entity type ID uses the reserved `core.` namespace. */
    public function registerEntityType(EntityTypeInterface $type, ?string $registrant = null): void;

    public function registerCoreEntityType(EntityTypeInterface $type, ?string $registrant = null): void;

    /** @return array<string, EntityTypeInterface> */
    public function getDefinitions(): array;

    public function hasDefinition(string $entityTypeId): bool;

    public function getStorage(string $entityTypeId): Storage\EntityStorageInterface;

    /**
     * Returns the framework {@see EntityRepositoryInterface} for an entity type.
     *
     * Requires a repository factory to be configured on the manager (kernel wiring).
     */
    public function getRepository(string $entityTypeId): EntityRepositoryInterface;
}

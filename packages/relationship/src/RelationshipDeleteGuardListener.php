<?php

declare(strict_types=1);

namespace Waaseyaa\Relationship;

use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Event\EntityEvent;

final class RelationshipDeleteGuardListener
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly string $guardedEntityType = 'node',
    ) {}

    public function __invoke(EntityEvent $event): void
    {
        $entity = $event->entity;
        if ($entity->getEntityTypeId() !== $this->guardedEntityType) {
            return;
        }

        $entityId = $entity->id();
        if ($entityId === null) {
            return;
        }

        if (!$this->entityTypeManager->hasDefinition('relationship')) {
            return;
        }

        $relationshipStorage = $this->entityTypeManager->getStorage('relationship');
        $idString = (string) $entityId;

        $outbound = $relationshipStorage->getQuery()
            ->condition('from_entity_type', $this->guardedEntityType)
            ->condition('from_entity_id', $idString)
            ->accessCheck(false)
            ->execute();
        $inbound = $relationshipStorage->getQuery()
            ->condition('to_entity_type', $this->guardedEntityType)
            ->condition('to_entity_id', $idString)
            ->accessCheck(false)
            ->execute();

        $linkedIds = array_values(array_unique([
            ...$outbound,
            ...$inbound,
        ]));
        if ($linkedIds === []) {
            return;
        }

        sort($linkedIds);

        throw new \RuntimeException(sprintf(
            'Safe-delete blocked for %s %s: linked relationship IDs [%s].',
            $this->guardedEntityType,
            $idString,
            implode(', ', array_map(static fn($id): string => (string) $id, $linkedIds)),
        ));
    }
}

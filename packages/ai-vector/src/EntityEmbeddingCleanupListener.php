<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Vector;

use Waaseyaa\Entity\Event\EntityEvent;

final class EntityEmbeddingCleanupListener
{
    public function __construct(
        private readonly EmbeddingStorageInterface $storage,
    ) {}

    public function onPostDelete(EntityEvent $event): void
    {
        $entityId = $event->entity->id();
        if ($entityId === null || $entityId === '') {
            return;
        }

        $this->storage->delete(
            $event->entity->getEntityTypeId(),
            (string) $entityId,
        );
    }
}

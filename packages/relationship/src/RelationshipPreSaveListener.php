<?php

declare(strict_types=1);

namespace Waaseyaa\Relationship;

use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\FieldableInterface;

final class RelationshipPreSaveListener
{
    public function __construct(
        private readonly RelationshipValidator $validator,
    ) {}

    public function __invoke(EntityEvent $event): void
    {
        if ($event->entity->getEntityTypeId() !== 'relationship') {
            return;
        }

        $normalized = $this->validator->normalize($event->entity->toArray());
        $this->validator->assertValid($normalized);

        if ($event->entity instanceof FieldableInterface) {
            foreach ($normalized as $field => $value) {
                $event->entity->set($field, $value);
            }
        }
    }
}

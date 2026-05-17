<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Event;

use Waaseyaa\Foundation\Event\DomainEvent;

/**
 * @api
 */
final class EntitySaved extends DomainEvent
{
    public function __construct(
        string $entityTypeId,
        string $entityId,
        public readonly array $changedFields,
        public readonly bool $isNew,
        ?string $tenantId = null,
        ?string $actorId = null,
    ) {
        parent::__construct(
            aggregateType: $entityTypeId,
            aggregateId: $entityId,
            tenantId: $tenantId,
            actorId: $actorId,
        );
    }

    public function getPayload(): array
    {
        return [
            'entity_type' => $this->aggregateType,
            'entity_id' => $this->aggregateId,
            'changed_fields' => $this->changedFields,
            'is_new' => $this->isNew,
        ];
    }
}

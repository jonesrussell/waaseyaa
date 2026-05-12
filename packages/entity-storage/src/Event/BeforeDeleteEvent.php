<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Event;

use Waaseyaa\Entity\EntityInterface;

/**
 * @api
 *
 * Dispatched before any backend delete in a delete operation.
 *
 * Subscribers may throw {@see AbortOperationException} to halt the delete.
 * No backend deletes occur after an abort; no {@see AfterDeleteEvent} fires.
 *
 * @see AfterDeleteEvent
 * @see AbortOperationException
 */
final class BeforeDeleteEvent implements EntityLifecycleEventInterface
{
    public function __construct(
        private readonly EntityInterface $entityValue,
    ) {}

    public function entity(): EntityInterface
    {
        return $this->entityValue;
    }
}

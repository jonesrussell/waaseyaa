<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Event;

use Waaseyaa\Entity\EntityInterface;

/**
 * @api
 *
 * Dispatched after ALL backend deletes succeed in a delete operation.
 *
 * This event is NOT dispatched on partial failure or when a {@see BeforeDeleteEvent}
 * subscriber throws {@see AbortOperationException}.
 *
 * @see BeforeDeleteEvent
 * @see AbortOperationException
 */
final class AfterDeleteEvent implements EntityLifecycleEventInterface
{
    public function __construct(
        private readonly EntityInterface $entityValue,
    ) {}

    public function entity(): EntityInterface
    {
        return $this->entityValue;
    }
}

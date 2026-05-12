<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Event;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\EntityStorage\SaveContext;

/**
 * @api
 *
 * Dispatched after ALL backend writes succeed in a save operation.
 *
 * This event is NOT dispatched on {@see \Waaseyaa\EntityStorage\Exception\PartialSaveException}
 * or when a {@see BeforeSaveEvent} subscriber throws {@see AbortOperationException}.
 *
 * @see BeforeSaveEvent
 * @see \Waaseyaa\EntityStorage\Exception\PartialSaveException
 */
final class AfterSaveEvent implements EntityLifecycleEventInterface
{
    public function __construct(
        private readonly EntityInterface $entityValue,
        private readonly SaveContext $saveContextValue,
        private readonly bool $newRevision,
    ) {}

    public function entity(): EntityInterface
    {
        return $this->entityValue;
    }

    /**
     * The save context carrying flags for this operation (e.g. withoutNewRevision).
     */
    public function saveContext(): SaveContext
    {
        return $this->saveContextValue;
    }

    /**
     * Whether this save created a new revision.
     */
    public function isNewRevision(): bool
    {
        return $this->newRevision;
    }
}

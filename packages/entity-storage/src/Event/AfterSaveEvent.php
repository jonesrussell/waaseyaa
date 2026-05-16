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
    /**
     * @param list<string>|null $affectedLangcodes List of langcodes touched in this save.
     *                                              Null = inferred via $entity->activeLangcode()
     *                                              by consumers. Backfilled by the M-006
     *                                              translatable write path
     *                                              ({@see \Waaseyaa\EntityStorage\CoordinatorLifecycleDispatcher}).
     */
    public function __construct(
        private readonly EntityInterface $entityValue,
        private readonly SaveContext $saveContextValue,
        private readonly bool $newRevision,
        private readonly ?array $affectedLangcodes = null,
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

    /**
     * List of langcodes touched in this save.
     *
     * Null = no per-langcode information available; consumers infer via
     * $entity->activeLangcode(). Non-null = a sorted, unique list of the
     * langcodes actually written. Used by listing-cache invalidation to
     * emit per-langcode tag invalidations on multi-language saves.
     *
     * @return list<string>|null
     */
    public function affectedLangcodes(): ?array
    {
        return $this->affectedLangcodes;
    }
}

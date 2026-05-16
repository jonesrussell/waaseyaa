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
    /**
     * @param list<string>|null $affectedLangcodes List of langcodes whose translation
     *                                              rows were removed by this delete.
     *                                              Null = inferred via
     *                                              $entity->activeLangcode() by consumers.
     *                                              Backfilled by the M-006 translatable
     *                                              write path
     *                                              ({@see \Waaseyaa\EntityStorage\CoordinatorLifecycleDispatcher}).
     */
    public function __construct(
        private readonly EntityInterface $entityValue,
        private readonly ?array $affectedLangcodes = null,
    ) {}

    public function entity(): EntityInterface
    {
        return $this->entityValue;
    }

    /**
     * List of langcodes whose translation rows were removed by this delete.
     *
     * Null = no per-langcode information available; consumers infer via
     * $entity->activeLangcode(). Non-null = a sorted, unique list of the
     * langcodes whose translation rows existed before the delete. Used by
     * listing-cache invalidation to emit per-langcode tag invalidations.
     *
     * @return list<string>|null
     */
    public function affectedLangcodes(): ?array
    {
        return $this->affectedLangcodes;
    }
}

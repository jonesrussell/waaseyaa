<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Event;

use Waaseyaa\Entity\EntityInterface;

/**
 * @api
 *
 * Marker interface for all entity lifecycle events dispatched by the coordinator.
 *
 * All Before- and After-prefixed events implement this interface and carry the
 * entity that triggered the operation.
 *
 * @see BeforeSaveEvent
 * @see AfterSaveEvent
 * @see BeforeDeleteEvent
 * @see AfterDeleteEvent
 */
interface EntityLifecycleEventInterface
{
    /**
     * The entity for which the lifecycle operation is executing.
     */
    public function entity(): EntityInterface;
}

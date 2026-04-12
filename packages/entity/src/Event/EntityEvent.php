<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Waaseyaa\Entity\EntityInterface;

final class EntityEvent extends Event
{
    public function __construct(
        public readonly EntityInterface $entity,
        public readonly ?EntityInterface $originalEntity = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\EntityBase;

final class NonHydratableEntity extends EntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct(
            $values,
            'non_hydratable',
            ['id' => 'id', 'label' => 'label'],
        );
    }
}

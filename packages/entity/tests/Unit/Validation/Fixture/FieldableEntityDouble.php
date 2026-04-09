<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Validation\Fixture;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\FieldableInterface;

/**
 * @internal For validation unit tests that need EntityInterface&FieldableInterface mocks.
 */
interface FieldableEntityDouble extends EntityInterface, FieldableInterface
{
}

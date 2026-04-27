<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;

use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class BadUnion extends ContentEntityBase
{
    #[Field]
    public string|int $either = 'x';
}

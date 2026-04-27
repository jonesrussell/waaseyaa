<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;

use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class BadUnknownType extends ContentEntityBase
{
    #[Field(type: 'integerr')]
    public string $count = '';
}

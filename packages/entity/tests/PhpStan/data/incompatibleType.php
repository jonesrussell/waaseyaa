<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;

use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class BadIncompatible extends ContentEntityBase
{
    #[Field(type: 'integer')]
    public string $count = '';
}

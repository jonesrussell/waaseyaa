<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;

use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

final class GoodCompatibleOverride extends ContentEntityBase
{
    #[Field(type: 'text')]
    public string $body = '';
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\PhpStan\Fixtures;

use Waaseyaa\Entity\Attribute\Field;

final class JustADto
{
    #[Field]
    public string $name = '';
}

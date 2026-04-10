<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use Waaseyaa\Entity\ConfigEntityBase;

/**
 * Concrete config entity for ConfigEntityBase / duplicate() tests (P3).
 */
final class TestConfigEntity extends ConfigEntityBase
{
    protected string $entityTypeId = 'test_config';
}

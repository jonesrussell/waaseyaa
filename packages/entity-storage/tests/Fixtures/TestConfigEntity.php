<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\ConfigEntityBase;

/**
 * Test config entity class for storage tests.
 */
class TestConfigEntity extends ConfigEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = 'test_config',
        array $entityKeys = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys);
    }

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function set(string $name, mixed $value): static
    {
        $this->values[$name] = $value;

        return $this;
    }

    public function hasField(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }
}

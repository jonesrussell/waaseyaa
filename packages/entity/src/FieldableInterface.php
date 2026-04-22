<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

use Waaseyaa\Field\FieldDefinitionInterface;

interface FieldableInterface
{
    public function hasField(string $name): bool;

    public function get(string $name): mixed;

    public function set(string $name, mixed $value): static;

    /** @return array<string, FieldDefinitionInterface> Field definitions keyed by field name */
    public function getFieldDefinitions(): array;
}

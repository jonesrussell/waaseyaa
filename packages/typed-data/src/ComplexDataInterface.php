<?php

declare(strict_types=1);

namespace Waaseyaa\TypedData;

/**
 * @api
 */
interface ComplexDataInterface extends TypedDataInterface, \Traversable
{
    public function get(string $name): TypedDataInterface;

    public function set(string $name, mixed $value): static;

    /** @return array<string, DataDefinitionInterface> */
    public function getProperties(): array;

    public function toArray(): array;
}

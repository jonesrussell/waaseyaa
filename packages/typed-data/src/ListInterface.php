<?php

declare(strict_types=1);

namespace Aurora\TypedData;

interface ListInterface extends TypedDataInterface, \Countable, \Traversable
{
    public function get(int $index): TypedDataInterface;

    public function set(int $index, mixed $value): void;

    public function first(): ?TypedDataInterface;

    public function isEmpty(): bool;

    public function appendItem(mixed $value = null): TypedDataInterface;

    public function removeItem(int $index): void;
}

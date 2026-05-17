<?php

declare(strict_types=1);

namespace Waaseyaa\Config;

/**
 * @api
 */
interface ConfigInterface
{
    public function getName(): string;

    public function get(string $key = ''): mixed;

    public function set(string $key, mixed $value): static;

    public function clear(string $key): static;

    public function delete(): static;

    public function save(): static;

    public function isNew(): bool;

    public function getRawData(): array;
}

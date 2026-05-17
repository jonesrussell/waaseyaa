<?php

declare(strict_types=1);

namespace Waaseyaa\Config;

/**
 * @api
 */
interface ConfigFactoryInterface
{
    public function get(string $name): ConfigInterface;

    public function getEditable(string $name): ConfigInterface;

    /** @return ConfigInterface[] */
    public function loadMultiple(array $names): array;

    public function rename(string $oldName, string $newName): static;

    /** @return string[] */
    public function listAll(string $prefix = ''): array;
}

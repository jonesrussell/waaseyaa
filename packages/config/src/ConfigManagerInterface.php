<?php

declare(strict_types=1);

namespace Waaseyaa\Config;

/**
 * @api
 */
interface ConfigManagerInterface
{
    public function getActiveStorage(): StorageInterface;

    public function getSyncStorage(): StorageInterface;

    public function import(): ConfigImportResult;

    public function export(): void;

    /** @return array<string, mixed> */
    public function diff(string $configName): array;
}

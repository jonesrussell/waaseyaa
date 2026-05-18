<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Mcp;

use Waaseyaa\Config\StorageInterface;

/**
 * Inert config storage used by {@see McpServiceProvider} when the host
 * has not wired a real config storage.
 *
 * Returning `false` from {@see read()} matches the contract that
 * {@see McpServersConfig::normalise()} treats as "no rows". This lets
 * the kernel boot on hosts that do not enable the config subsystem
 * (e.g. minimal CLI smoke tests) without producing warnings.
 *
 * Marked `@api` because the class is reachable only through the
 * {@see StorageInterface} contract — its interface-required methods are
 * never invoked at the concrete type and would otherwise read as dead
 * to the static analyser.
 *
 * @api
 */
final class NullConfigStorage implements StorageInterface
{
    public function exists(string $name): bool
    {
        return false;
    }

    public function read(string $name): array|false
    {
        return false;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function readMultiple(array $names): array
    {
        return [];
    }

    public function write(string $name, array $data): bool
    {
        return false;
    }

    public function delete(string $name): bool
    {
        return false;
    }

    public function rename(string $name, string $newName): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    public function listAll(string $prefix = ''): array
    {
        return [];
    }

    public function deleteAll(string $prefix = ''): bool
    {
        return false;
    }

    public function createCollection(string $collection): static
    {
        return $this;
    }

    public function getCollectionName(): string
    {
        return '';
    }

    /**
     * @return string[]
     */
    public function getAllCollectionNames(): array
    {
        return [];
    }
}

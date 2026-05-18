<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\PhaseN\AgentRuntime\Fixture;

use Waaseyaa\Config\StorageInterface;

/**
 * Minimal in-memory implementation of {@see StorageInterface} used by the
 * MCP integration tests to seed `config.ai.mcp_servers` without booting
 * the full kernel + sync subsystem.
 */
final class InMemoryConfigStorage implements StorageInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $data = [];

    private string $collection = '';

    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function read(string $name): array|false
    {
        return $this->data[$name] ?? false;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function readMultiple(array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            if (array_key_exists($name, $this->data)) {
                $out[$name] = $this->data[$name];
            }
        }

        return $out;
    }

    public function write(string $name, array $data): bool
    {
        $this->data[$name] = $data;

        return true;
    }

    public function delete(string $name): bool
    {
        if (!array_key_exists($name, $this->data)) {
            return false;
        }
        unset($this->data[$name]);

        return true;
    }

    public function rename(string $name, string $newName): bool
    {
        if (!array_key_exists($name, $this->data)) {
            return false;
        }
        $this->data[$newName] = $this->data[$name];
        unset($this->data[$name]);

        return true;
    }

    /**
     * @return string[]
     */
    public function listAll(string $prefix = ''): array
    {
        if ($prefix === '') {
            return array_keys($this->data);
        }

        return array_values(array_filter(
            array_keys($this->data),
            static fn(string $name): bool => str_starts_with($name, $prefix),
        ));
    }

    public function deleteAll(string $prefix = ''): bool
    {
        foreach (array_keys($this->data) as $name) {
            if ($prefix === '' || str_starts_with($name, $prefix)) {
                unset($this->data[$name]);
            }
        }

        return true;
    }

    public function createCollection(string $collection): static
    {
        $clone = clone $this;
        $clone->collection = $collection;

        return $clone;
    }

    public function getCollectionName(): string
    {
        return $this->collection;
    }

    /**
     * @return string[]
     */
    public function getAllCollectionNames(): array
    {
        return [];
    }
}

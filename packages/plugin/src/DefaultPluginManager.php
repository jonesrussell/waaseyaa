<?php

declare(strict_types=1);

namespace Aurora\Plugin;

use Aurora\Cache\CacheBackendInterface;
use Aurora\Plugin\Discovery\PluginDiscoveryInterface;
use Aurora\Plugin\Factory\ContainerFactory;
use Aurora\Plugin\Factory\PluginFactoryInterface;

class DefaultPluginManager implements PluginManagerInterface
{
    /** @var array<string, Definition\PluginDefinition>|null */
    private ?array $definitions = null;

    private readonly PluginFactoryInterface $factory;

    public function __construct(
        private readonly PluginDiscoveryInterface $discovery,
        private readonly ?CacheBackendInterface $cache = null,
        private readonly string $cacheKey = 'plugin_definitions',
        ?PluginFactoryInterface $factory = null,
    ) {
        $this->factory = $factory ?? new ContainerFactory();
    }

    public function getDefinition(string $pluginId): Definition\PluginDefinition
    {
        $definitions = $this->getDefinitions();
        if (!isset($definitions[$pluginId])) {
            throw new \InvalidArgumentException("Plugin '$pluginId' does not exist.");
        }
        return $definitions[$pluginId];
    }

    public function getDefinitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        // Try cache
        if ($this->cache !== null) {
            $cached = $this->cache->get($this->cacheKey);
            if ($cached !== false && $cached->valid) {
                $this->definitions = $cached->data;
                $this->syncFactory();
                return $this->definitions;
            }
        }

        // Discover
        $this->definitions = $this->discovery->getDefinitions();

        // Store in cache
        if ($this->cache !== null) {
            $this->cache->set($this->cacheKey, $this->definitions);
        }

        $this->syncFactory();
        return $this->definitions;
    }

    public function hasDefinition(string $pluginId): bool
    {
        return isset($this->getDefinitions()[$pluginId]);
    }

    public function createInstance(string $pluginId, array $configuration = []): PluginInspectionInterface
    {
        // Ensure definitions are loaded
        $this->getDefinitions();
        return $this->factory->createInstance($pluginId, $configuration);
    }

    public function clearCachedDefinitions(): void
    {
        $this->definitions = null;
        if ($this->cache !== null) {
            $this->cache->delete($this->cacheKey);
        }
    }

    private function syncFactory(): void
    {
        if ($this->factory instanceof ContainerFactory && $this->definitions !== null) {
            $this->factory->setDefinitions($this->definitions);
        }
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Plugin\Factory;

use Aurora\Plugin\Definition\PluginDefinition;
use Aurora\Plugin\PluginInspectionInterface;

final class ContainerFactory implements PluginFactoryInterface
{
    /** @var array<string, PluginDefinition> */
    private array $definitions = [];

    public function setDefinitions(array $definitions): void
    {
        $this->definitions = $definitions;
    }

    public function createInstance(string $pluginId, array $configuration = []): PluginInspectionInterface
    {
        if (!isset($this->definitions[$pluginId])) {
            throw new \InvalidArgumentException("Plugin '$pluginId' not found.");
        }

        $definition = $this->definitions[$pluginId];
        $class = $definition->class;

        return new $class($pluginId, $definition, $configuration);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Plugin;

/**
 * @api
 */
interface PluginManagerInterface
{
    public function getDefinition(string $pluginId): Definition\PluginDefinition;

    /** @return array<string, Definition\PluginDefinition> */
    public function getDefinitions(): array;

    public function hasDefinition(string $pluginId): bool;

    public function createInstance(string $pluginId, array $configuration = []): PluginInspectionInterface;
}

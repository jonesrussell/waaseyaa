<?php

declare(strict_types=1);

namespace Aurora\Plugin;

abstract class PluginBase implements PluginInspectionInterface
{
    public function __construct(
        protected readonly string $pluginId,
        protected readonly Definition\PluginDefinition $pluginDefinition,
        protected readonly array $configuration = [],
    ) {}

    public function getPluginId(): string
    {
        return $this->pluginId;
    }

    public function getPluginDefinition(): Definition\PluginDefinition
    {
        return $this->pluginDefinition;
    }
}

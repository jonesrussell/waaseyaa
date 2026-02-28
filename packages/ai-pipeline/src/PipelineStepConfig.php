<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline;

/**
 * Value object for step configuration within a pipeline.
 *
 * Represents the configuration of a single step in a pipeline entity,
 * including which plugin to use, its label, execution order, and
 * step-specific configuration.
 */
final readonly class PipelineStepConfig
{
    /**
     * @param string $id Step ID within the pipeline.
     * @param string $pluginId Plugin ID of the step implementation.
     * @param string $label Human-readable label.
     * @param int $weight Execution order (lower weights execute first).
     * @param array<string, mixed> $configuration Step-specific configuration.
     */
    public function __construct(
        public string $id,
        public string $pluginId,
        public string $label = '',
        public int $weight = 0,
        public array $configuration = [],
    ) {}
}

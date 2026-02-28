<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline;

/**
 * Interface for pipeline step plugins.
 *
 * Each step in a pipeline implements this interface. Steps receive input data
 * (from the previous step or the pipeline trigger) and return a StepResult
 * containing output data for the next step.
 */
interface PipelineStepInterface
{
    /**
     * Process input data and return output data.
     *
     * @param array<string, mixed> $input Input data from previous step (or pipeline trigger).
     * @param PipelineContext $context Pipeline execution context.
     *
     * @return StepResult The result of this step.
     */
    public function process(array $input, PipelineContext $context): StepResult;

    /**
     * Get a human-readable description of what this step does.
     */
    public function describe(): string;
}

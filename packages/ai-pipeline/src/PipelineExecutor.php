<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline;

/**
 * Executes pipelines synchronously.
 *
 * Runs each step in weight order, passing the output of each step as input
 * to the next. Stops execution on failure or if a step requests a halt.
 */
final class PipelineExecutor
{
    /**
     * @param array<string, PipelineStepInterface> $stepPlugins Available step plugins, keyed by plugin ID.
     */
    public function __construct(
        private readonly array $stepPlugins = [],
    ) {}

    /**
     * Execute a pipeline synchronously.
     *
     * @param Pipeline $pipeline The pipeline to execute.
     * @param array<string, mixed> $input Initial input data for the first step.
     *
     * @return PipelineResult The result of the pipeline execution.
     */
    public function execute(Pipeline $pipeline, array $input = []): PipelineResult
    {
        $startTime = hrtime(true);
        $steps = $pipeline->getSteps();
        $stepResults = [];
        $currentInput = $input;
        $pipelineId = (string) ($pipeline->id() ?? '');

        $context = new PipelineContext(
            pipelineId: $pipelineId,
            startedAt: time(),
        );

        if ($steps === []) {
            $durationMs = (hrtime(true) - $startTime) / 1_000_000;

            return new PipelineResult(
                success: true,
                stepResults: [],
                finalOutput: $input,
                message: 'Pipeline has no steps.',
                durationMs: $durationMs,
            );
        }

        foreach ($steps as $stepConfig) {
            $plugin = $this->stepPlugins[$stepConfig->pluginId] ?? null;

            if ($plugin === null) {
                $result = StepResult::failure(
                    \sprintf('Step plugin "%s" not found.', $stepConfig->pluginId),
                );
                $stepResults[] = $result;

                $durationMs = (hrtime(true) - $startTime) / 1_000_000;

                return new PipelineResult(
                    success: false,
                    stepResults: $stepResults,
                    finalOutput: $currentInput,
                    message: \sprintf('Pipeline failed: step plugin "%s" not found.', $stepConfig->pluginId),
                    durationMs: $durationMs,
                );
            }

            $result = $plugin->process($currentInput, $context);
            $stepResults[] = $result;

            if (!$result->success) {
                $durationMs = (hrtime(true) - $startTime) / 1_000_000;

                return new PipelineResult(
                    success: false,
                    stepResults: $stepResults,
                    finalOutput: $result->output,
                    message: \sprintf('Pipeline failed at step "%s": %s', $stepConfig->id, $result->message),
                    durationMs: $durationMs,
                );
            }

            $currentInput = $result->output;

            if ($result->stopPipeline) {
                $durationMs = (hrtime(true) - $startTime) / 1_000_000;

                return new PipelineResult(
                    success: true,
                    stepResults: $stepResults,
                    finalOutput: $result->output,
                    message: \sprintf('Pipeline halted at step "%s": %s', $stepConfig->id, $result->message),
                    durationMs: $durationMs,
                );
            }
        }

        $durationMs = (hrtime(true) - $startTime) / 1_000_000;

        return new PipelineResult(
            success: true,
            stepResults: $stepResults,
            finalOutput: $currentInput,
            message: 'Pipeline completed successfully.',
            durationMs: $durationMs,
        );
    }
}

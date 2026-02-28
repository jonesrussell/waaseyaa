<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline;

/**
 * Value object for full pipeline execution results.
 *
 * Contains the overall success/failure status, individual step results,
 * the final output data, and execution timing information.
 */
final readonly class PipelineResult
{
    /**
     * @param bool $success Whether the pipeline completed successfully.
     * @param StepResult[] $stepResults Results from each executed step.
     * @param array<string, mixed> $finalOutput Output from the last successful step.
     * @param string $message Summary message.
     * @param float $durationMs Total execution time in milliseconds.
     */
    public function __construct(
        public bool $success,
        public array $stepResults,
        public array $finalOutput,
        public string $message,
        public float $durationMs,
    ) {}
}

<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline;

/**
 * Value object representing the result of a single pipeline step execution.
 */
final readonly class StepResult
{
    /**
     * @param bool $success Whether the step completed successfully.
     * @param array<string, mixed> $output Output data passed to next step.
     * @param string $message Human-readable message.
     * @param bool $stopPipeline If true, stop pipeline after this step.
     */
    public function __construct(
        public bool $success,
        public array $output = [],
        public string $message = '',
        public bool $stopPipeline = false,
    ) {}

    /**
     * Create a successful step result.
     *
     * @param array<string, mixed> $output
     */
    public static function success(array $output = [], string $message = ''): self
    {
        return new self(success: true, output: $output, message: $message);
    }

    /**
     * Create a failed step result.
     *
     * @param array<string, mixed> $output
     */
    public static function failure(string $message, array $output = []): self
    {
        return new self(success: false, output: $output, message: $message);
    }

    /**
     * Create a successful step result that halts the pipeline.
     *
     * The step itself succeeds, but no further steps are executed.
     *
     * @param array<string, mixed> $output
     */
    public static function halt(string $message, array $output = []): self
    {
        return new self(success: true, output: $output, message: $message, stopPipeline: true);
    }
}

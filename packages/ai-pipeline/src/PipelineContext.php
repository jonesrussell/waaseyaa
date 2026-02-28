<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline;

/**
 * Execution context for pipeline steps.
 *
 * Provides shared state that persists across all steps in a pipeline execution.
 * Steps can use this to communicate information outside the normal input/output flow.
 */
final class PipelineContext
{
    /**
     * @var array<string, mixed>
     */
    private array $state = [];

    public function __construct(
        public readonly string $pipelineId,
        public readonly int $startedAt,
    ) {}

    /**
     * Get a value from the context state.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /**
     * Set a value in the context state.
     */
    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /**
     * Get all context state.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->state;
    }
}

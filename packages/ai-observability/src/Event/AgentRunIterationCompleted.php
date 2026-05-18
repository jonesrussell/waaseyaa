<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Event;

/**
 * Domain event emitted at the close of an executor iteration.
 *
 * Carries the per-iteration wall-clock duration so the telemetry
 * aggregator can build the `iteration_durations_ms` list documented
 * in the data-model.
 *
 * @api
 */
final readonly class AgentRunIterationCompleted
{
    public function __construct(
        public string $runId,
        public int $iterationIndex,
        public int $durationMs,
    ) {}
}

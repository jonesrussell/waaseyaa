<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Event;

/**
 * Domain event emitted when an AgentRun reaches a terminal status
 * (`completed`, `failed`, or `cancelled`).
 *
 * Triggers the telemetry flush — the aggregator writes the per-run
 * record to {@see \Waaseyaa\AI\Observability\Recorder\AgentTelescopeRecorderInterface},
 * updates the AgentRun row, and emits Prometheus metrics.
 *
 * @api
 */
final readonly class AgentRunTerminated
{
    public function __construct(
        public string $runId,
        public string $status,
        public ?string $errorCode,
        public \DateTimeImmutable $finishedAt,
    ) {}
}

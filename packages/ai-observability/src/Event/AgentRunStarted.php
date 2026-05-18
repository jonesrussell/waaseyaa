<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Event;

/**
 * Domain event emitted when an AgentRun transitions from `queued`
 * to `running`.
 *
 * Emitted by the WP04 `RunAgentHandler` after `markRunning()` succeeds;
 * the {@see \Waaseyaa\AI\Observability\Listener\AgentRunTelemetryListener}
 * uses it to initialise the per-run telemetry aggregator.
 *
 * @api
 */
final readonly class AgentRunStarted
{
    public function __construct(
        public string $runId,
        public ?string $agentDefinitionId,
        public ?int $accountId,
        public \DateTimeImmutable $startedAt,
    ) {}
}

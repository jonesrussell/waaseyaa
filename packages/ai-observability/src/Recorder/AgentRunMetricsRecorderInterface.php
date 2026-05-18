<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Recorder;

/**
 * Prometheus / metrics surface for AgentRun telemetry.
 *
 * Defined in L5 so the listener does not need to import the L6
 * Telescope-Prometheus bridge directly; the application kernel binds
 * the concrete implementation that forwards to the metrics registry.
 *
 * Counters / histograms emitted by this contract:
 *
 * - `waaseyaa_agent_run_total{status, agent_id}` — counter incremented
 *   once per terminal AgentRun.
 * - `waaseyaa_agent_run_wall_clock_ms` — histogram observing total
 *   wall-clock duration in milliseconds.
 * - `waaseyaa_agent_provider_tokens_total{provider, model, direction}` —
 *   counter incremented by provider call token usage as it lands.
 *
 * @api
 */
interface AgentRunMetricsRecorderInterface
{
    /**
     * Increment the terminal-status counter and observe wall-clock duration.
     */
    public function recordTerminalRun(
        string $status,
        ?string $agentDefinitionId,
        ?int $wallClockMs,
    ): void;

    /**
     * Record token usage for a single provider call.
     */
    public function recordProviderTokens(
        string $provider,
        string $model,
        int $tokensIn,
        int $tokensOut,
    ): void;
}

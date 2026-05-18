<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Recorder;

/**
 * No-op metrics recorder; default DI binding when Prometheus is not wired.
 *
 * @api
 */
final class NullAgentRunMetricsRecorder implements AgentRunMetricsRecorderInterface
{
    public function recordTerminalRun(
        string $status,
        ?string $agentDefinitionId,
        ?int $wallClockMs,
    ): void {
        // intentionally a no-op
    }

    public function recordProviderTokens(
        string $provider,
        string $model,
        int $tokensIn,
        int $tokensOut,
    ): void {
        // intentionally a no-op
    }
}

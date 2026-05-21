<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Broadcast;

/**
 * Push events for an in-flight agent run onto the SSE broadcast channel.
 *
 * The handler emits the event vocabulary defined in
 * `kitty-specs/agent-executor-01KRWPK7/data-model.md` § "SSE event vocabulary"
 * (`run_started`, `iteration`, `tool_call_*`, `provider_chunk`,
 * `approval_*`, `run_completed`, `run_failed`, `run_cancelled`).
 *
 * Implementations MUST be best-effort: a failure to deliver an event
 * must not propagate to the run loop. {@see AgentRunBroadcaster} is the
 * canonical implementation, bound by {@see AgentRunBroadcasterServiceProvider}.
 *
 * @api
 */
interface AgentRunBroadcasterInterface
{
    /**
     * Push an SSE event onto the `agent.run.<runId>` channel.
     *
     * @param string $runId UUID of the {@see \Waaseyaa\AI\Agent\Entity\AgentRun}.
     * @param string $event Event name (e.g. `run_started`, `run_completed`).
     * @param array<string, mixed> $data Event payload (excluding `run_id`, which the broadcaster MUST inject).
     */
    public function push(string $runId, string $event, array $data): void;
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Recorder;

/**
 * Recorder contract for terminal-status AgentRun telemetry entries.
 *
 * This interface lives in the AI Observability layer (L5) so that the
 * listener can depend on a stable contract without importing from the
 * Interfaces layer (L6, where `waaseyaa/telescope` lives). The application
 * kernel binds the production implementation that forwards to Telescope
 * (or any other observability sink) via DI; tests use
 * {@see NullAgentTelescopeRecorder} or a recording double.
 *
 * The record shape is the canonical telemetry envelope documented in
 * `kitty-specs/agent-executor-01KRWPK7/data-model.md` § "Audit invariants".
 *
 * @api
 */
interface AgentTelescopeRecorderInterface
{
    /**
     * Persist a terminal-status AgentRun telemetry record.
     *
     * Implementations MUST be idempotent for identical `run_id` values;
     * the listener flushes exactly once per terminal status but a retry
     * (e.g. transport failover) MUST NOT corrupt downstream state.
     *
     * @param array{
     *   run_id: string,
     *   agent_definition_id: string|null,
     *   account_id: int|null,
     *   tokens_in: int,
     *   tokens_out: int,
     *   cost_cents: int|null,
     *   tool_call_count: int,
     *   wall_clock_ms: int|null,
     *   iteration_durations_ms: list<int>,
     *   status: string,
     *   error_code: string|null,
     *   started_at: string|null,
     *   finished_at: string|null,
     * } $record
     */
    public function recordAgentRun(array $record): void;
}

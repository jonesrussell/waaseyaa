<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Event;

/**
 * Domain event emitted once per terminal tool-call lifecycle event
 * (`tool_call_completed` / `tool_call_failed`).
 *
 * The aggregator only counts terminal tool-call boundaries — the
 * `tool_call_count` field is documented as a cumulative tally of
 * dispatched tools, not in-flight starts.
 *
 * @api
 */
final readonly class AgentRunToolCallObserved
{
    public function __construct(
        public string $runId,
        public string $toolName,
        public bool $succeeded,
    ) {}
}

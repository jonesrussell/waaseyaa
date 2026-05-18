<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Enum;

/**
 * Human-in-the-loop (HITL) destructive-approval mode for an
 * {@see \Waaseyaa\AI\Agent\Entity\AgentRun}.
 *
 *  - `None`        — no approval gate; tool calls run without pause.
 *  - `All`         — every destructive tool call pauses the run for approval.
 *  - `Interactive` — heuristic / per-call approval gate (UI-driven).
 *
 * Persisted in `agent_run.destructive_approval`.
 *
 * @api
 */
enum HitlMode: string
{
    case None = 'none';
    case All = 'all';
    case Interactive = 'interactive';
}

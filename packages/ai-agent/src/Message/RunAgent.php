<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Symfony Messenger envelope identifying which {@see \Waaseyaa\AI\Agent\Entity\AgentRun}
 * a worker should execute.
 *
 * Carries only the run id — the handler reloads the row through
 * {@see \Waaseyaa\AI\Agent\Repository\AgentRunRepository::find()} so the message
 * remains tiny on the wire and the worker always observes the freshest
 * persisted state (notably status / cancellation / approval transitions
 * issued between dispatch and pickup).
 *
 * Authoritative shape: `kitty-specs/agent-executor-01KRWPK7/data-model.md`
 * § "Messages > RunAgent".
 *
 * @api
 */
final readonly class RunAgent
{
    public function __construct(public Uuid $runId) {}
}

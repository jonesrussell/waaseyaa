<?php

declare(strict_types=1);

namespace Aurora\AI\Agent;

/**
 * Interface for AI agents that can execute actions within the CMS.
 *
 * Agents operate as a specific user within the permission model,
 * support dry-run mode for previewing changes, and produce
 * structured results with audit logging.
 */
interface AgentInterface
{
    /**
     * Execute the agent's action.
     *
     * @return AgentResult The result of execution
     */
    public function execute(AgentContext $context): AgentResult;

    /**
     * Dry-run: preview what the agent would do without making changes.
     *
     * @return AgentResult The proposed changes (not applied)
     */
    public function dryRun(AgentContext $context): AgentResult;

    /**
     * Get the agent's description of what it does.
     */
    public function describe(): string;
}

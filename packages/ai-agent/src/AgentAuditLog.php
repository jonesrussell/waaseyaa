<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent;

/**
 * Audit log entry for agent executions.
 *
 * Records the agent ID, user account, action type, success status,
 * and any associated data for each agent operation.
 * @api
 */
final readonly class AgentAuditLog
{
    /**
     * Unix timestamp of the log entry.
     */
    public int $timestamp;

    /**
     * @param string $agentId Plugin ID of the agent
     * @param int $accountId User ID the agent acted as
     * @param string $action Action type: 'execute', 'dry_run', or 'tool_call'
     * @param bool $success Whether the action succeeded
     * @param string $message Human-readable description
     * @param array<string, mixed> $data Structured log data
     * @param int $timestamp Unix timestamp (defaults to current time)
     */
    public function __construct(
        public string $agentId,
        public int $accountId,
        public string $action,
        public bool $success,
        public string $message,
        public array $data = [],
        int $timestamp = 0,
    ) {
        $this->timestamp = $timestamp !== 0 ? $timestamp : \time();
    }
}

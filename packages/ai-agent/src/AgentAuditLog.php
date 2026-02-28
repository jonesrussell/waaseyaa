<?php

declare(strict_types=1);

namespace Aurora\AI\Agent;

/**
 * Audit log entry for agent executions.
 *
 * Records the agent ID, user account, action type, success status,
 * and any associated data for each agent operation.
 */
final readonly class AgentAuditLog
{
    /**
     * @param string $agentId Plugin ID of the agent
     * @param int $accountId User ID the agent acted as
     * @param string $action Action type: 'execute', 'dry_run', or 'tool_call'
     * @param bool $success Whether the action succeeded
     * @param string $message Human-readable description
     * @param array<string, mixed> $data Structured log data
     * @param int $timestamp Unix timestamp of the log entry
     */
    public function __construct(
        public string $agentId,
        public int $accountId,
        public string $action,
        public bool $success,
        public string $message,
        public array $data = [],
        public int $timestamp = 0,
    ) {}
}

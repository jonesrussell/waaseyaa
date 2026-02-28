<?php

declare(strict_types=1);

namespace Aurora\AI\Agent;

use Aurora\AI\Schema\Mcp\McpToolExecutor;

/**
 * Executes agents with safety guarantees and audit logging.
 *
 * Wraps agent execution in try/catch, logs all executions to an
 * in-memory audit log, and provides MCP tool execution on behalf
 * of agents.
 */
final class AgentExecutor
{
    /** @var AgentAuditLog[] */
    private array $auditLog = [];

    public function __construct(
        private readonly McpToolExecutor $toolExecutor,
    ) {}

    /**
     * Execute an agent in normal mode.
     */
    public function execute(AgentInterface $agent, AgentContext $context): AgentResult
    {
        $agentId = $this->getAgentId($agent);
        $accountId = (int) $context->account->id();

        try {
            $result = $agent->execute($context);

            $this->auditLog[] = new AgentAuditLog(
                agentId: $agentId,
                accountId: $accountId,
                action: 'execute',
                success: $result->success,
                message: $result->message,
                data: $result->data,
                timestamp: \time(),
            );

            return $result;
        } catch (\Throwable $e) {
            $result = AgentResult::failure(
                message: "Agent execution failed: {$e->getMessage()}",
                data: ['exception' => $e::class],
            );

            $this->auditLog[] = new AgentAuditLog(
                agentId: $agentId,
                accountId: $accountId,
                action: 'execute',
                success: false,
                message: $result->message,
                data: $result->data,
                timestamp: \time(),
            );

            return $result;
        }
    }

    /**
     * Execute an agent in dry-run mode.
     */
    public function dryRun(AgentInterface $agent, AgentContext $context): AgentResult
    {
        $agentId = $this->getAgentId($agent);
        $accountId = (int) $context->account->id();

        try {
            $result = $agent->dryRun($context);

            $this->auditLog[] = new AgentAuditLog(
                agentId: $agentId,
                accountId: $accountId,
                action: 'dry_run',
                success: $result->success,
                message: $result->message,
                data: $result->data,
                timestamp: \time(),
            );

            return $result;
        } catch (\Throwable $e) {
            $result = AgentResult::failure(
                message: "Agent dry-run failed: {$e->getMessage()}",
                data: ['exception' => $e::class],
            );

            $this->auditLog[] = new AgentAuditLog(
                agentId: $agentId,
                accountId: $accountId,
                action: 'dry_run',
                success: false,
                message: $result->message,
                data: $result->data,
                timestamp: \time(),
            );

            return $result;
        }
    }

    /**
     * Execute an MCP tool call on behalf of an agent.
     *
     * @param string $toolName The MCP tool name to call
     * @param array<string, mixed> $arguments Tool input arguments
     * @param AgentContext $context The agent context
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    public function executeTool(string $toolName, array $arguments, AgentContext $context): array
    {
        $accountId = (int) $context->account->id();

        try {
            $result = $this->toolExecutor->execute($toolName, $arguments);
            $isError = $result['isError'] ?? false;

            $this->auditLog[] = new AgentAuditLog(
                agentId: 'tool',
                accountId: $accountId,
                action: 'tool_call',
                success: !$isError,
                message: "Tool call: {$toolName}",
                data: [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                ],
                timestamp: \time(),
            );

            return $result;
        } catch (\Throwable $e) {
            $this->auditLog[] = new AgentAuditLog(
                agentId: 'tool',
                accountId: $accountId,
                action: 'tool_call',
                success: false,
                message: "Tool call failed: {$toolName} - {$e->getMessage()}",
                data: [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'exception' => $e::class,
                ],
                timestamp: \time(),
            );

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => \json_encode(['error' => $e->getMessage()], \JSON_THROW_ON_ERROR),
                    ],
                ],
                'isError' => true,
            ];
        }
    }

    /**
     * Get the audit log.
     *
     * @return AgentAuditLog[]
     */
    public function getAuditLog(): array
    {
        return $this->auditLog;
    }

    /**
     * Derive an agent identifier string.
     */
    private function getAgentId(AgentInterface $agent): string
    {
        return $agent::class;
    }
}

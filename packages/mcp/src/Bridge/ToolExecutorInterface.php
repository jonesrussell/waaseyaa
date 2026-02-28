<?php

declare(strict_types=1);

namespace Aurora\Mcp\Bridge;

/**
 * Interface for executing MCP tool calls.
 *
 * Abstracts the McpToolExecutor so the MCP endpoint can be tested
 * independently of the final concrete class.
 */
interface ToolExecutorInterface
{
    /**
     * Execute an MCP tool call.
     *
     * @param string $toolName The tool name (e.g. "create_node", "read_user").
     * @param array<string, mixed> $arguments The tool input arguments.
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    public function execute(string $toolName, array $arguments): array;
}

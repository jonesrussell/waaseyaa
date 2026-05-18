<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp\Bridge;

use Waaseyaa\AI\Tools\AgentTool;

/**
 * Interface for accessing MCP tool descriptors.
 *
 * Abstracts {@see \Waaseyaa\AI\Tools\ToolRegistryInterface} so the MCP
 * endpoint can be tested independently of the final concrete class.
 *
 * @api
 */
interface ToolRegistryInterface
{
    /**
     * Get all agent-tool definitions exposed over MCP.
     *
     * @return AgentTool[]
     */
    public function getTools(): array;

    /**
     * Get a specific agent tool by name.
     */
    public function getTool(string $name): ?AgentTool;
}

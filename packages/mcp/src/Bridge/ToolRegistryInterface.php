<?php

declare(strict_types=1);

namespace Aurora\Mcp\Bridge;

use Aurora\AI\Schema\Mcp\McpToolDefinition;

/**
 * Interface for accessing MCP tool definitions.
 *
 * Abstracts the SchemaRegistry so the MCP endpoint can be tested
 * independently of the final concrete class.
 */
interface ToolRegistryInterface
{
    /**
     * Get all MCP tool definitions.
     *
     * @return McpToolDefinition[]
     */
    public function getTools(): array;

    /**
     * Get a specific MCP tool by name.
     */
    public function getTool(string $name): ?McpToolDefinition;
}

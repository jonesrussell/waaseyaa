<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Tools;

/**
 * Catalogue of {@see AgentTool} instances exposed by the framework.
 *
 * The framework ships {@see \Waaseyaa\AI\Tools\Catalogue\AttributeToolRegistry}
 * as the canonical implementation; third-party packages can compose
 * additional registries (e.g. for runtime-registered MCP server tools)
 * around it.
 *
 * @api
 */
interface ToolRegistryInterface
{
    /**
     * Register a tool in the catalogue. Implementations MAY reject
     * duplicates with the same name.
     */
    public function register(AgentTool $tool): void;

    /**
     * Look up a tool by name. Throws when no such tool is registered.
     *
     * @throws ToolNotFoundException
     */
    public function get(string $name): AgentTool;

    /**
     * Whether a tool with the supplied name is registered.
     */
    public function has(string $name): bool;

    /**
     * Iterate every registered tool.
     *
     * @return iterable<AgentTool>
     */
    public function all(): iterable;
}

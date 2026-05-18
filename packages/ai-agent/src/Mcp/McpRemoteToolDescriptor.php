<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Mcp;

/**
 * Descriptor for a single tool advertised by a remote MCP server.
 *
 * One descriptor maps to exactly one local `AgentTool` registration in the
 * tool catalogue, prefixed by the configured server alias.
 *
 * `metadata` carries any non-standard hints supplied by the server (e.g. a
 * `destructive: false` opt-out). It is intentionally `array<string, mixed>`
 * because the MCP spec does not constrain server-specific extensions.
 *
 * @api
 */
final readonly class McpRemoteToolDescriptor
{
    /**
     * @param string $name Server-side tool name (e.g. "create_issue").
     * @param string $description Human-readable description supplied by the server.
     * @param array<string, mixed> $inputSchema JSON Schema for tool input parameters.
     * @param array<string, mixed> $metadata Non-standard hints (destructive opt-out, etc.).
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public array $metadata = [],
    ) {}
}

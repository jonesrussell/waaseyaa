<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Mcp;

/**
 * Result of an MCP `initialize` handshake against a remote server.
 *
 * Per spec FR-021 / C-008 we only support Streamable-HTTP MCP servers; this
 * DTO summarises the protocol-level identity that the server self-reports so
 * the catalogue source can record server identity in audit logs.
 *
 * @api
 */
final readonly class McpServerInfo
{
    /**
     * @param string $name Server-reported name (e.g. "github-mcp").
     * @param string $version Server-reported version (free-form).
     * @param string $protocolVersion MCP protocol version reported by the server.
     * @param array<string, mixed> $capabilities Raw `capabilities` object from the initialize response.
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $protocolVersion,
        public array $capabilities,
    ) {}
}

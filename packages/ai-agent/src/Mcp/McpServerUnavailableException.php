<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Mcp;

/**
 * Raised by {@see StreamableHttpMcpClient} when a remote MCP server is
 * unreachable, returns a 5xx, or sends a malformed envelope.
 *
 * `McpClientToolSource::bootstrap()` catches this per-server so an outage
 * on one provider degrades gracefully (the affected tools simply do not
 * appear in the catalogue) without breaking the rest of the kernel boot.
 *
 * @api
 */
final class McpServerUnavailableException extends \RuntimeException
{
    public function __construct(
        public readonly string $url,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

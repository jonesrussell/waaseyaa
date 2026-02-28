<?php

declare(strict_types=1);

namespace Aurora\Mcp;

final readonly class McpResponse
{
    public function __construct(
        public string $body,
        public int $statusCode = 200,
        public string $contentType = 'application/json',
    ) {}
}

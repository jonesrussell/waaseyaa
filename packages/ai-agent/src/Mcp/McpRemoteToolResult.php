<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Mcp;

/**
 * Result of a remote MCP `tools/call` invocation.
 *
 * Mirrors the MCP result shape:
 * `{ isError: bool, content: [ { type, text|data, ... }, ... ] }`.
 *
 * @api
 */
final readonly class McpRemoteToolResult
{
    /**
     * @param bool $isError Whether the server flagged the call as an error.
     * @param list<array<string, mixed>> $content Raw `content` blocks from the server.
     */
    public function __construct(
        public bool $isError,
        public array $content,
    ) {}

    /**
     * Serialise to the local `ToolRegistryInterface::execute()` return shape.
     *
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    public function toRegistryShape(): array
    {
        $blocks = [];
        foreach ($this->content as $block) {
            $type = isset($block['type']) && is_string($block['type']) ? $block['type'] : 'text';
            $text = isset($block['text']) && is_string($block['text'])
                ? $block['text']
                : json_encode($block, JSON_THROW_ON_ERROR);
            $blocks[] = ['type' => $type, 'text' => $text];
        }

        $out = ['content' => $blocks];
        if ($this->isError) {
            $out['isError'] = true;
        }

        return $out;
    }
}

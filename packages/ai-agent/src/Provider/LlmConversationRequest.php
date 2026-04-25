<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

/**
 * Provider-agnostic LLM conversation (messages, optional system, tools, limits).
 *
 * Use {@see MessageRequest::conversation()} for compatibility with existing call sites.
 */
final readonly class LlmConversationRequest
{
    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $messages,
        public ?string $system = null,
        public array $tools = [],
        public int $maxTokens = 4096,
        public array $metadata = [],
    ) {}

    /**
     * Serialize to Anthropic Messages API body fragment (excludes `model`).
     *
     * @return array<string, mixed>
     */
    public function toAnthropicFragment(): array
    {
        $data = [
            'messages' => $this->messages,
            'max_tokens' => $this->maxTokens,
        ];

        if ($this->system !== null) {
            $data['system'] = $this->system;
        }

        if ($this->tools !== []) {
            $data['tools'] = $this->tools;
        }

        if ($this->metadata !== []) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    /**
     * Serialize to an OpenAI-compatible `chat/completions` JSON body fragment (excludes `model`).
     *
     * Tool definitions and multi-modal content are not fully bridged yet; use
     * {@see AnthropicProvider} for MCP / tool loops until adapters mature.
     *
     * @return array<string, mixed>
     */
    public function toOpenAiChatFragment(): array
    {
        if ($this->tools !== []) {
            throw new \InvalidArgumentException(
                'LlmConversationRequest: non-empty tools are not supported for OpenAI-compatible serialization yet.',
            );
        }

        $openAiMessages = [];
        if ($this->system !== null && $this->system !== '') {
            $openAiMessages[] = ['role' => 'system', 'content' => $this->system];
        }

        foreach ($this->messages as $msg) {
            $openAiMessages[] = self::mapAnthropicStyleMessageToOpenAi($msg);
        }

        return [
            'messages' => $openAiMessages,
            'max_tokens' => $this->maxTokens,
        ];
    }

    /**
     * @param array<string, mixed> $msg
     * @return array{role: string, content: string}
     */
    private static function mapAnthropicStyleMessageToOpenAi(array $msg): array
    {
        $role = $msg['role'] ?? 'user';
        if ($role !== 'assistant' && $role !== 'user') {
            $role = 'user';
        }

        $content = $msg['content'] ?? '';
        if (\is_string($content)) {
            return ['role' => $role, 'content' => $content];
        }

        if (!\is_array($content)) {
            return ['role' => $role, 'content' => ''];
        }

        $textParts = [];
        foreach ($content as $block) {
            if (!\is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text') {
                $textParts[] = (string) ($block['text'] ?? '');
            }
        }

        $flattened = implode("\n", array_filter($textParts, static fn(string $s): bool => $s !== ''));

        return ['role' => $role, 'content' => $flattened];
    }
}

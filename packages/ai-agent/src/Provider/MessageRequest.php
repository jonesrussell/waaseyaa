<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

final readonly class MessageRequest
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
     * Neutral conversation view for multi-provider serialization.
     */
    public function conversation(): LlmConversationRequest
    {
        return new LlmConversationRequest(
            messages: $this->messages,
            system: $this->system,
            tools: $this->tools,
            maxTokens: $this->maxTokens,
            metadata: $this->metadata,
        );
    }

    /**
     * Serialize to Anthropic API request format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->conversation()->toAnthropicFragment();
    }
}

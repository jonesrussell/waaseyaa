<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Provider;

/**
 * Deterministic LLM provider for development and testing.
 *
 * Returns a fixed placeholder response without network I/O. Do NOT use in
 * production - every request returns the same placeholder regardless of input.
 *
 * Apps can bind this as the default implementation of {@see ProviderInterface}
 * in dev/test environments, then rebind to a real provider (e.g.
 * {@see AnthropicProvider}) once credentials are configured.
 */
final class NullLlmProvider implements ProviderInterface
{
    public const PLACEHOLDER = '[LLM unavailable in this environment - configure an LLM provider to enable AI features.]';

    public function sendMessage(MessageRequest $request): MessageResponse
    {
        return new MessageResponse(
            content: [
                [
                    'type' => 'text',
                    'text' => self::PLACEHOLDER,
                ],
            ],
            stopReason: 'end_turn',
            usage: [
                'input_tokens' => 0,
                'output_tokens' => 0,
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Provider\LlmConversationRequest;

#[CoversClass(LlmConversationRequest::class)]
final class LlmConversationRequestTest extends TestCase
{
    public function testToAnthropicFragmentMatchesLegacyShape(): void
    {
        $c = new LlmConversationRequest(
            messages: [['role' => 'user', 'content' => 'Hi']],
            system: 'Be brief.',
            maxTokens: 512,
        );

        $this->assertSame(
            [
                'messages' => [['role' => 'user', 'content' => 'Hi']],
                'max_tokens' => 512,
                'system' => 'Be brief.',
            ],
            $c->toAnthropicFragment(),
        );
    }

    public function testToOpenAiChatFragmentMapsSystemAndUser(): void
    {
        $c = new LlmConversationRequest(
            messages: [['role' => 'user', 'content' => 'Hello']],
            system: 'You are helpful.',
            maxTokens: 100,
        );

        $this->assertSame(
            [
                'messages' => [
                    ['role' => 'system', 'content' => 'You are helpful.'],
                    ['role' => 'user', 'content' => 'Hello'],
                ],
                'max_tokens' => 100,
            ],
            $c->toOpenAiChatFragment(),
        );
    }

    public function testToOpenAiChatFragmentFlattensTextBlocks(): void
    {
        $c = new LlmConversationRequest(
            messages: [
                [
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'text', 'text' => 'Line one'],
                        ['type' => 'text', 'text' => 'Line two'],
                    ],
                ],
            ],
        );

        $fragment = $c->toOpenAiChatFragment();
        $this->assertSame(
            "Line one\nLine two",
            $fragment['messages'][0]['content'],
        );
    }

    public function testToOpenAiChatFragmentRejectsTools(): void
    {
        $c = new LlmConversationRequest(
            messages: [['role' => 'user', 'content' => 'x']],
            tools: [['name' => 't', 'description' => 'd', 'input_schema' => ['type' => 'object']]],
        );

        $this->expectException(\InvalidArgumentException::class);
        $c->toOpenAiChatFragment();
    }
}

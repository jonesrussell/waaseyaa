<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Provider\OpenAiCompatibleProvider;

#[CoversClass(OpenAiCompatibleProvider::class)]
final class OpenAiCompatibleProviderTest extends TestCase
{
    public function testParseChatCompletionResponseMapsTextAndUsage(): void
    {
        $data = [
            'choices' => [
                [
                    'message' => ['role' => 'assistant', 'content' => 'Hello back'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 3,
            ],
        ];

        $response = OpenAiCompatibleProvider::parseChatCompletionResponse($data);
        $this->assertSame('Hello back', $response->getText());
        $this->assertSame('end_turn', $response->stopReason);
        $this->assertSame(10, $response->usage['input_tokens'] ?? null);
        $this->assertSame(3, $response->usage['output_tokens'] ?? null);
    }

    public function testParseChatCompletionResponseMapsToolCallsFinish(): void
    {
        $data = [
            'choices' => [
                [
                    'message' => ['role' => 'assistant', 'content' => ''],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [],
        ];

        $response = OpenAiCompatibleProvider::parseChatCompletionResponse($data);
        $this->assertSame('tool_use', $response->stopReason);
    }
}

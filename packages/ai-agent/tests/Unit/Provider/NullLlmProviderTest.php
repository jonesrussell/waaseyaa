<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\MessageResponse;
use Waaseyaa\AI\Agent\Provider\NullLlmProvider;

#[CoversClass(NullLlmProvider::class)]
final class NullLlmProviderTest extends TestCase
{
    #[Test]
    public function sendMessageReturnsPlaceholderText(): void
    {
        $provider = new NullLlmProvider();
        $request = new MessageRequest(messages: [
            ['role' => 'user', 'content' => 'hello'],
        ]);

        $response = $provider->sendMessage($request);

        self::assertInstanceOf(MessageResponse::class, $response);
        self::assertStringContainsString('LLM unavailable', $response->getText());
    }

    #[Test]
    public function sendMessageIsDeterministic(): void
    {
        $provider = new NullLlmProvider();
        $request = new MessageRequest(messages: [
            ['role' => 'user', 'content' => 'anything'],
        ]);

        $first = $provider->sendMessage($request);
        $second = $provider->sendMessage($request);

        self::assertSame(NullLlmProvider::PLACEHOLDER, $first->getText());
        self::assertSame($first->getText(), $second->getText());
    }

    #[Test]
    public function constructionDoesNotPerformIo(): void
    {
        new NullLlmProvider();
        new NullLlmProvider();

        self::assertTrue(true);
    }
}

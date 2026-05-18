<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Message;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Waaseyaa\AI\Agent\Message\RunAgentHandler;

/**
 * Surface-only check for {@see RunAgentHandler}: the class must carry
 * the {@see AsMessageHandler} attribute so Symfony Messenger discovers
 * it automatically.
 *
 * Behavioural tests live in
 * `tests/Integration/PhaseN/AgentRuntime/EnqueueAndConsumeTest.php`.
 */
#[CoversNothing]
final class RunAgentHandlerTest extends TestCase
{
    #[Test]
    public function isMarkedAsMessageHandler(): void
    {
        $reflection = new \ReflectionClass(RunAgentHandler::class);
        $attributes = $reflection->getAttributes(AsMessageHandler::class);
        self::assertNotEmpty($attributes, 'RunAgentHandler must carry #[AsMessageHandler].');
    }

    #[Test]
    public function exposesInvokeContract(): void
    {
        $reflection = new \ReflectionClass(RunAgentHandler::class);
        self::assertTrue($reflection->hasMethod('__invoke'));
        $invoke = $reflection->getMethod('__invoke');
        self::assertSame('void', (string) $invoke->getReturnType());
    }
}

<?php

declare(strict_types=1);

namespace Aurora\AI\Agent\Tests\Unit;

use Aurora\Access\AccountInterface;
use Aurora\AI\Agent\AgentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentContext::class)]
final class AgentContextTest extends TestCase
{
    public function testConstruction(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(42);

        $context = new AgentContext(
            account: $account,
            parameters: ['title' => 'Hello World'],
            dryRun: false,
        );

        self::assertSame($account, $context->account);
        self::assertSame(['title' => 'Hello World'], $context->parameters);
        self::assertFalse($context->dryRun);
    }

    public function testDefaultValues(): void
    {
        $account = $this->createMock(AccountInterface::class);

        $context = new AgentContext(account: $account);

        self::assertSame([], $context->parameters);
        self::assertFalse($context->dryRun);
    }

    public function testDryRunFlag(): void
    {
        $account = $this->createMock(AccountInterface::class);

        $context = new AgentContext(
            account: $account,
            dryRun: true,
        );

        self::assertTrue($context->dryRun);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(AgentContext::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }
}

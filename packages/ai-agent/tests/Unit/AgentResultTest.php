<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit;

use Waaseyaa\AI\Agent\AgentAction;
use Waaseyaa\AI\Agent\AgentResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentResult::class)]
final class AgentResultTest extends TestCase
{
    public function testConstruction(): void
    {
        $action = new AgentAction('create', 'Created a node');
        $result = new AgentResult(
            success: true,
            message: 'Agent completed',
            data: ['id' => 1],
            actions: [$action],
        );

        self::assertTrue($result->success);
        self::assertSame('Agent completed', $result->message);
        self::assertSame(['id' => 1], $result->data);
        self::assertCount(1, $result->actions);
        self::assertSame($action, $result->actions[0]);
    }

    public function testDefaultValues(): void
    {
        $result = new AgentResult(
            success: true,
            message: 'Done',
        );

        self::assertSame([], $result->data);
        self::assertSame([], $result->actions);
    }

    public function testSuccessFactory(): void
    {
        $action = new AgentAction('update', 'Updated a node');
        $result = AgentResult::success(
            message: 'Created successfully',
            data: ['entity_type' => 'node'],
            actions: [$action],
        );

        self::assertTrue($result->success);
        self::assertSame('Created successfully', $result->message);
        self::assertSame(['entity_type' => 'node'], $result->data);
        self::assertCount(1, $result->actions);
    }

    public function testSuccessFactoryDefaults(): void
    {
        $result = AgentResult::success('Done');

        self::assertTrue($result->success);
        self::assertSame('Done', $result->message);
        self::assertSame([], $result->data);
        self::assertSame([], $result->actions);
    }

    public function testFailureFactory(): void
    {
        $result = AgentResult::failure(
            message: 'Permission denied',
            data: ['reason' => 'insufficient_access'],
        );

        self::assertFalse($result->success);
        self::assertSame('Permission denied', $result->message);
        self::assertSame(['reason' => 'insufficient_access'], $result->data);
        self::assertSame([], $result->actions);
    }

    public function testFailureFactoryDefaults(): void
    {
        $result = AgentResult::failure('Something went wrong');

        self::assertFalse($result->success);
        self::assertSame('Something went wrong', $result->message);
        self::assertSame([], $result->data);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(AgentResult::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    public function testTokenAndCostFieldsDefaultToZero(): void
    {
        $result = AgentResult::success('done');

        self::assertSame(0, $result->tokenUsageIn);
        self::assertSame(0, $result->tokenUsageOut);
        self::assertNull($result->costCents);
    }

    public function testSuccessFactoryCarriesTelemetry(): void
    {
        $result = AgentResult::success(
            message: 'done',
            tokenUsageIn: 1024,
            tokenUsageOut: 256,
            costCents: 12,
        );

        self::assertSame(1024, $result->tokenUsageIn);
        self::assertSame(256, $result->tokenUsageOut);
        self::assertSame(12, $result->costCents);
    }

    public function testFailureFactoryCarriesTelemetry(): void
    {
        $result = AgentResult::failure(
            message: 'boom',
            tokenUsageIn: 50,
            tokenUsageOut: 0,
            costCents: 1,
        );

        self::assertSame(50, $result->tokenUsageIn);
        self::assertSame(0, $result->tokenUsageOut);
        self::assertSame(1, $result->costCents);
    }
}

<?php

declare(strict_types=1);

namespace Aurora\AI\Agent\Tests\Unit;

use Aurora\AI\Agent\AgentAuditLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentAuditLog::class)]
final class AgentAuditLogTest extends TestCase
{
    public function testConstruction(): void
    {
        $log = new AgentAuditLog(
            agentId: 'content_creator',
            accountId: 1,
            action: 'execute',
            success: true,
            message: 'Created node successfully',
            data: ['node_id' => 42],
            timestamp: 1700000000,
        );

        self::assertSame('content_creator', $log->agentId);
        self::assertSame(1, $log->accountId);
        self::assertSame('execute', $log->action);
        self::assertTrue($log->success);
        self::assertSame('Created node successfully', $log->message);
        self::assertSame(['node_id' => 42], $log->data);
        self::assertSame(1700000000, $log->timestamp);
    }

    public function testDefaultValues(): void
    {
        $log = new AgentAuditLog(
            agentId: 'test_agent',
            accountId: 5,
            action: 'dry_run',
            success: true,
            message: 'Dry run completed',
        );

        self::assertSame([], $log->data);
        self::assertSame(0, $log->timestamp);
    }

    public function testToolCallAction(): void
    {
        $log = new AgentAuditLog(
            agentId: 'tool',
            accountId: 3,
            action: 'tool_call',
            success: false,
            message: 'Tool call failed: create_node',
            data: ['tool' => 'create_node', 'error' => 'Permission denied'],
        );

        self::assertSame('tool_call', $log->action);
        self::assertFalse($log->success);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(AgentAuditLog::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }
}

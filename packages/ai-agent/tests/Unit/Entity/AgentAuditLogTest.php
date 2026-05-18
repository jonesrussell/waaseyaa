<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Entity\AgentAuditLog;
use Waaseyaa\AI\Agent\Enum\EventType;

#[CoversClass(AgentAuditLog::class)]
final class AgentAuditLogTest extends TestCase
{
    #[Test]
    public function forFactoryProducesIsNewEntityWithDefaults(): void
    {
        $occurredAt = new \DateTimeImmutable('2026-05-18T12:00:00+00:00');

        $log = AgentAuditLog::for(
            id: 'evt-1',
            runId: 'run-1',
            iteration: 3,
            eventType: EventType::ToolCall,
            occurredAt: $occurredAt,
            toolName: 'entity.read',
            toolArgumentsJson: '{"id":"node-1"}',
        );

        self::assertSame('agent_audit_log', $log->getEntityTypeId());
        self::assertSame('evt-1', $log->id());
        self::assertSame('run-1', $log->getRunId());
        self::assertSame(EventType::ToolCall, $log->getEventType());
        self::assertSame(3, (int) $log->get('iteration'));
        self::assertSame('entity.read', $log->get('tool_name'));
        self::assertSame('{"id":"node-1"}', $log->get('tool_arguments_json'));
        self::assertTrue($log->isSuccess());
        self::assertTrue($log->isNew(), 'for() must return an isNew entity ready for append().');
    }

    #[Test]
    public function forFactoryMergesExtraOverrides(): void
    {
        $log = AgentAuditLog::for(
            id: 'evt-2',
            runId: 'run-2',
            iteration: 1,
            eventType: EventType::Error,
            occurredAt: new \DateTimeImmutable('2026-05-18T13:00:00+00:00'),
            success: false,
            extra: ['tool_result_summary' => 'upstream 502'],
        );

        self::assertFalse($log->isSuccess());
        self::assertSame('upstream 502', $log->get('tool_result_summary'));
        self::assertSame(EventType::Error, $log->getEventType());
    }
}

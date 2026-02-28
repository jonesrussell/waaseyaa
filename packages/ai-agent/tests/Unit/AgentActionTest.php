<?php

declare(strict_types=1);

namespace Aurora\AI\Agent\Tests\Unit;

use Aurora\AI\Agent\AgentAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentAction::class)]
final class AgentActionTest extends TestCase
{
    public function testConstruction(): void
    {
        $action = new AgentAction(
            type: 'create',
            description: 'Created a new article node',
            data: ['entity_type' => 'node', 'bundle' => 'article'],
        );

        self::assertSame('create', $action->type);
        self::assertSame('Created a new article node', $action->description);
        self::assertSame(['entity_type' => 'node', 'bundle' => 'article'], $action->data);
    }

    public function testDefaultData(): void
    {
        $action = new AgentAction(
            type: 'delete',
            description: 'Deleted node 42',
        );

        self::assertSame('delete', $action->type);
        self::assertSame('Deleted node 42', $action->description);
        self::assertSame([], $action->data);
    }

    public function testToolCallType(): void
    {
        $action = new AgentAction(
            type: 'tool_call',
            description: 'Called create_node tool',
            data: ['tool' => 'create_node', 'arguments' => ['attributes' => ['title' => 'Test']]],
        );

        self::assertSame('tool_call', $action->type);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(AgentAction::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }
}

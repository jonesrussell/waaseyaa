<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Dsl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Dsl\TaskDefinition;

#[CoversClass(TaskDefinition::class)]
final class TaskDefinitionTest extends TestCase
{
    #[Test]
    public function it_holds_task_properties(): void
    {
        $task = new TaskDefinition(
            operation: 'add_field',
            entityType: 'article',
            field: 'subtitle',
            parameters: ['type' => 'string', 'label' => 'Subtitle'],
        );

        $this->assertSame('add_field', $task->operation);
        $this->assertSame('article', $task->entityType);
        $this->assertSame('subtitle', $task->field);
        $this->assertSame(['type' => 'string', 'label' => 'Subtitle'], $task->parameters);
    }

    #[Test]
    public function field_is_nullable(): void
    {
        $task = new TaskDefinition(
            operation: 'add_entity_type',
            entityType: 'event',
            parameters: ['label' => 'Event'],
        );

        $this->assertNull($task->field);
    }

    #[Test]
    public function to_array_serializes(): void
    {
        $task = new TaskDefinition('add_field', 'node', 'body', ['type' => 'text']);

        $this->assertSame([
            'operation' => 'add_field',
            'entity_type' => 'node',
            'field' => 'body',
            'parameters' => ['type' => 'text'],
        ], $task->toArray());
    }
}

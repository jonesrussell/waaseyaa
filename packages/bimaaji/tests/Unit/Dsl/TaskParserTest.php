<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Dsl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Dsl\TaskDefinition;
use Waaseyaa\Bimaaji\Dsl\TaskParser;

#[CoversClass(TaskParser::class)]
final class TaskParserTest extends TestCase
{
    private TaskParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TaskParser();
    }

    #[Test]
    public function it_parses_add_field_task(): void
    {
        $json = json_encode([
            'operation' => 'add_field',
            'entity_type' => 'article',
            'field' => 'subtitle',
            'parameters' => ['type' => 'string', 'label' => 'Subtitle'],
        ], JSON_THROW_ON_ERROR);

        $task = $this->parser->parseJson($json);

        $this->assertInstanceOf(TaskDefinition::class, $task);
        $this->assertSame('add_field', $task->operation);
        $this->assertSame('article', $task->entityType);
        $this->assertSame('subtitle', $task->field);
    }

    #[Test]
    public function it_parses_entity_type_creation(): void
    {
        $json = json_encode([
            'operation' => 'add_entity_type',
            'entity_type' => 'event',
            'parameters' => ['label' => 'Event', 'keys' => ['id' => 'id', 'uuid' => 'uuid']],
        ], JSON_THROW_ON_ERROR);

        $task = $this->parser->parseJson($json);

        $this->assertSame('add_entity_type', $task->operation);
        $this->assertNull($task->field);
        $this->assertSame('Event', $task->parameters['label']);
    }

    #[Test]
    public function it_throws_on_missing_operation(): void
    {
        $json = json_encode(['entity_type' => 'node'], JSON_THROW_ON_ERROR);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('operation');

        $this->parser->parseJson($json);
    }

    #[Test]
    public function it_throws_on_missing_entity_type(): void
    {
        $json = json_encode(['operation' => 'add_field'], JSON_THROW_ON_ERROR);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('entity_type');

        $this->parser->parseJson($json);
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parseJson('{invalid');
    }

    #[Test]
    public function it_parses_array_input(): void
    {
        $data = [
            'operation' => 'add_field',
            'entity_type' => 'node',
            'field' => 'tags',
            'parameters' => ['type' => 'entity_reference'],
        ];

        $task = $this->parser->parseArray($data);

        $this->assertSame('add_field', $task->operation);
        $this->assertSame('tags', $task->field);
    }
}

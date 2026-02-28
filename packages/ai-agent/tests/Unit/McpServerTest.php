<?php

declare(strict_types=1);

namespace Aurora\AI\Agent\Tests\Unit;

use Aurora\AI\Agent\McpServer;
use Aurora\AI\Schema\EntityJsonSchemaGenerator;
use Aurora\AI\Schema\Mcp\McpToolDefinition;
use Aurora\AI\Schema\Mcp\McpToolExecutor;
use Aurora\AI\Schema\Mcp\McpToolGenerator;
use Aurora\AI\Schema\SchemaRegistry;
use Aurora\Entity\EntityTypeInterface;
use Aurora\Entity\EntityTypeManagerInterface;
use Aurora\Entity\Storage\EntityStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpServer::class)]
final class McpServerTest extends TestCase
{
    private EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject $entityTypeManager;
    private McpServer $server;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

        $schemaGenerator = new EntityJsonSchemaGenerator($this->entityTypeManager);
        $toolGenerator = new McpToolGenerator($this->entityTypeManager);
        $registry = new SchemaRegistry($schemaGenerator, $toolGenerator);

        $executor = new McpToolExecutor($this->entityTypeManager);
        $this->server = new McpServer($registry, $executor);
    }

    public function testListToolsReturnsToolsFromRegistry(): void
    {
        $nodeType = $this->createMock(EntityTypeInterface::class);
        $nodeType->method('getLabel')->willReturn('Node');

        $this->entityTypeManager
            ->method('getDefinitions')
            ->willReturn(['node' => $nodeType]);

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($nodeType);

        $result = $this->server->listTools();

        self::assertArrayHasKey('tools', $result);
        // 5 tools per entity type: create, read, update, delete, query
        self::assertCount(5, $result['tools']);

        $toolNames = array_column($result['tools'], 'name');
        self::assertContains('create_node', $toolNames);
        self::assertContains('read_node', $toolNames);
        self::assertContains('update_node', $toolNames);
        self::assertContains('delete_node', $toolNames);
        self::assertContains('query_node', $toolNames);

        // Each tool should have name, description, and inputSchema
        foreach ($result['tools'] as $tool) {
            self::assertArrayHasKey('name', $tool);
            self::assertArrayHasKey('description', $tool);
            self::assertArrayHasKey('inputSchema', $tool);
        }
    }

    public function testListToolsEmptyRegistry(): void
    {
        $this->entityTypeManager
            ->method('getDefinitions')
            ->willReturn([]);

        $result = $this->server->listTools();

        self::assertSame(['tools' => []], $result);
    }

    public function testCallToolDelegatesToExecutor(): void
    {
        $nodeType = $this->createMock(EntityTypeInterface::class);
        $nodeType->method('getLabel')->willReturn('Node');

        $this->entityTypeManager
            ->method('getDefinitions')
            ->willReturn(['node' => $nodeType]);

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($nodeType);

        $this->entityTypeManager
            ->method('hasDefinition')
            ->willReturnCallback(fn (string $id) => $id === 'node');

        $entity = $this->createMock(\Aurora\Entity\EntityInterface::class);
        $entity->method('id')->willReturn('1');
        $entity->method('toArray')->willReturn(['title' => 'Test']);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('create')->willReturn($entity);
        $storage->method('save')->willReturn(1);

        $this->entityTypeManager
            ->method('getStorage')
            ->willReturn($storage);

        $result = $this->server->callTool('create_node', ['attributes' => ['title' => 'Test']]);

        self::assertArrayHasKey('content', $result);
        self::assertArrayNotHasKey('isError', $result);
        self::assertCount(1, $result['content']);
        self::assertSame('text', $result['content'][0]['type']);

        $decoded = json_decode($result['content'][0]['text'], true);
        self::assertSame('create', $decoded['operation']);
        self::assertSame('node', $decoded['entity_type']);
    }

    public function testCallToolWithUnknownToolReturnsError(): void
    {
        $this->entityTypeManager
            ->method('getDefinitions')
            ->willReturn([]);

        $result = $this->server->callTool('nonexistent_tool', []);

        self::assertTrue($result['isError']);
        self::assertCount(1, $result['content']);
        self::assertSame('text', $result['content'][0]['type']);

        $decoded = json_decode($result['content'][0]['text'], true);
        self::assertSame('Unknown tool: nonexistent_tool', $decoded['error']);
    }

    public function testCallToolWithExecutorError(): void
    {
        $nodeType = $this->createMock(EntityTypeInterface::class);
        $nodeType->method('getLabel')->willReturn('Node');

        $this->entityTypeManager
            ->method('getDefinitions')
            ->willReturn(['node' => $nodeType]);

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($nodeType);

        $this->entityTypeManager
            ->method('hasDefinition')
            ->willReturnCallback(fn (string $id) => $id === 'node');

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('load')->willReturn(null);

        $this->entityTypeManager
            ->method('getStorage')
            ->willReturn($storage);

        // read_node with ID 999 - entity not found
        $result = $this->server->callTool('read_node', ['id' => 999]);

        self::assertTrue($result['isError']);
    }
}

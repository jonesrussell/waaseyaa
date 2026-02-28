<?php

declare(strict_types=1);

namespace Aurora\Mcp\Tests\Unit;

use Aurora\Access\AccountInterface;
use Aurora\AI\Schema\Mcp\McpToolDefinition;
use Aurora\Mcp\Auth\McpAuthInterface;
use Aurora\Mcp\Bridge\ToolExecutorInterface;
use Aurora\Mcp\Bridge\ToolRegistryInterface;
use Aurora\Mcp\McpEndpoint;
use Aurora\Mcp\McpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpEndpoint::class)]
#[CoversClass(McpResponse::class)]
final class McpEndpointTest extends TestCase
{
    private McpAuthInterface $auth;
    private ToolRegistryInterface $registry;
    private ToolExecutorInterface $executor;
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->auth = $this->createMock(McpAuthInterface::class);
        $this->registry = $this->createMock(ToolRegistryInterface::class);
        $this->executor = $this->createMock(ToolExecutorInterface::class);
        $this->account = $this->createMock(AccountInterface::class);
        $this->account->method('id')->willReturn(1);
    }

    private function createEndpoint(): McpEndpoint
    {
        return new McpEndpoint(
            auth: $this->auth,
            registry: $this->registry,
            executor: $this->executor,
        );
    }

    #[Test]
    public function missingAuthHeaderReturns401(): void
    {
        $this->auth->method('authenticate')->willReturn(null);

        $endpoint = $this->createEndpoint();
        $response = $endpoint->handle(
            method: 'POST',
            body: '{"jsonrpc":"2.0","id":1,"method":"tools/list"}',
            authorizationHeader: null,
        );

        $this->assertSame(401, $response->statusCode);
        $decoded = \json_decode($response->body, true);
        $this->assertSame(-32001, $decoded['error']['code']);
        $this->assertSame('Unauthorized', $decoded['error']['message']);
    }

    #[Test]
    public function invalidTokenReturns401(): void
    {
        $this->auth->method('authenticate')->willReturn(null);

        $endpoint = $this->createEndpoint();
        $response = $endpoint->handle(
            method: 'POST',
            body: '{"jsonrpc":"2.0","id":1,"method":"tools/list"}',
            authorizationHeader: 'Bearer bad-token',
        );

        $this->assertSame(401, $response->statusCode);
    }

    #[Test]
    public function toolsListReturnsToolDefinitions(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);

        $tools = [
            new McpToolDefinition('create_node', 'Create a node.', [
                'type' => 'object',
                'properties' => ['attributes' => ['type' => 'object']],
                'required' => ['attributes'],
            ]),
        ];
        $this->registry->method('getTools')->willReturn($tools);

        $endpoint = $this->createEndpoint();
        $response = $endpoint->handle(
            method: 'POST',
            body: \json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
            ]),
            authorizationHeader: 'Bearer valid-token',
        );

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(1, $decoded['id']);
        $this->assertArrayHasKey('result', $decoded);
        $this->assertCount(1, $decoded['result']['tools']);
        $this->assertSame('create_node', $decoded['result']['tools'][0]['name']);
    }

    #[Test]
    public function toolsCallExecutesToolAndReturnsResult(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);

        $this->registry->method('getTools')->willReturn([
            new McpToolDefinition('read_node', 'Read a node.', [
                'type' => 'object',
                'properties' => [],
            ]),
        ]);

        $this->registry->method('getTool')
            ->with('read_node')
            ->willReturn(new McpToolDefinition('read_node', 'Read a node.', ['type' => 'object', 'properties' => []]));

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->with('read_node', ['id' => 42])
            ->willReturn([
                'content' => [
                    ['type' => 'text', 'text' => '{"operation":"read","id":42}'],
                ],
            ]);

        $endpoint = $this->createEndpoint();
        $response = $endpoint->handle(
            method: 'POST',
            body: \json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'read_node',
                    'arguments' => ['id' => 42],
                ],
            ]),
            authorizationHeader: 'Bearer valid-token',
        );

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertSame(2, $decoded['id']);
        $this->assertArrayHasKey('result', $decoded);
        $this->assertSame('text', $decoded['result']['content'][0]['type']);
    }

    #[Test]
    public function toolsCallWithUnknownToolReturnsError(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);
        $this->registry->method('getTool')->willReturn(null);

        $endpoint = $this->createEndpoint();
        $response = $endpoint->handle(
            method: 'POST',
            body: \json_encode([
                'jsonrpc' => '2.0',
                'id' => 3,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'nonexistent_tool',
                    'arguments' => [],
                ],
            ]),
            authorizationHeader: 'Bearer valid-token',
        );

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertArrayHasKey('error', $decoded);
    }

    #[Test]
    public function invalidJsonReturnsParseError(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);

        $endpoint = $this->createEndpoint();
        $response = $endpoint->handle(
            method: 'POST',
            body: '{invalid json',
            authorizationHeader: 'Bearer valid-token',
        );

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertSame(-32700, $decoded['error']['code']);
    }

    #[Test]
    public function missingMethodFieldReturnsInvalidRequest(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);

        $endpoint = $this->createEndpoint();
        $response = $endpoint->handle(
            method: 'POST',
            body: \json_encode(['jsonrpc' => '2.0', 'id' => 1]),
            authorizationHeader: 'Bearer valid-token',
        );

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertSame(-32600, $decoded['error']['code']);
    }

    #[Test]
    public function responseContentTypeIsJson(): void
    {
        $this->auth->method('authenticate')->willReturn(null);

        $endpoint = $this->createEndpoint();
        $response = $endpoint->handle(
            method: 'POST',
            body: '{}',
            authorizationHeader: null,
        );

        $this->assertSame('application/json', $response->contentType);
    }
}

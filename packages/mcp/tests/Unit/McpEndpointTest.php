<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\AI\Tools\AgentTool;
use Waaseyaa\AI\Tools\AgentToolInterface;
use Waaseyaa\AI\Tools\AgentToolResult;
use Waaseyaa\Mcp\Auth\McpAuthInterface;
use Waaseyaa\Mcp\Bridge\ToolExecutorInterface;
use Waaseyaa\Mcp\Bridge\ToolRegistryInterface;
use Waaseyaa\Mcp\McpEndpoint;
use Waaseyaa\Mcp\McpResponse;

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

    private function dispatch(McpEndpoint $endpoint, string $method, string $body, ?string $authorizationHeader): McpResponse
    {
        $headers = [];
        if ($authorizationHeader !== null) {
            $headers['HTTP_AUTHORIZATION'] = $authorizationHeader;
        }

        $request = HttpRequest::create('/_mcp', $method, [], [], [], $headers, $body);

        return $endpoint->handle($this->account, $request);
    }

    /**
     * Build a small {@see AgentTool} fixture backed by an anonymous-class
     * {@see AgentToolInterface} that returns a canned {@see AgentToolResult}.
     */
    private function makeTool(string $name, array $schema = []): AgentTool
    {
        $impl = new class implements AgentToolInterface {
            public function execute(array $arguments, AccountInterface $account): AgentToolResult
            {
                return AgentToolResult::success([['type' => 'text', 'text' => 'ok']]);
            }

            public function dryRun(array $arguments, AccountInterface $account): AgentToolResult
            {
                return AgentToolResult::error('dry_run_not_supported');
            }

            public function argumentsForAudit(array $arguments): array
            {
                return $arguments;
            }

            public function inputSchema(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            public function description(): string
            {
                return 'Test tool fixture.';
            }
        };

        return new AgentTool(
            name: $name,
            capability: 'tool.test',
            destructive: false,
            dryRunSupported: false,
            category: 'test',
            inputSchema: $schema !== [] ? $schema : ['type' => 'object', 'properties' => []],
            impl: $impl,
        );
    }

    #[Test]
    public function missingAuthHeaderReturns401(): void
    {
        $this->auth->method('authenticate')->willReturn(null);

        $endpoint = $this->createEndpoint();
        $response = $this->dispatch($endpoint, 'POST', '{"jsonrpc":"2.0","id":1,"method":"tools/list"}', null);

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
        $response = $this->dispatch($endpoint, 'POST', '{"jsonrpc":"2.0","id":1,"method":"tools/list"}', 'Bearer bad-token');

        $this->assertSame(401, $response->statusCode);
    }

    #[Test]
    public function toolsListReturnsToolDescriptors(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);

        $tool = $this->makeTool('create_node', [
            'type' => 'object',
            'properties' => ['attributes' => ['type' => 'object']],
            'required' => ['attributes'],
        ]);
        $this->registry->method('getTools')->willReturn([$tool]);

        $endpoint = $this->createEndpoint();
        $response = $this->dispatch($endpoint, 'POST', \json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]), 'Bearer valid-token');

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(1, $decoded['id']);
        $this->assertArrayHasKey('result', $decoded);
        $this->assertCount(1, $decoded['result']['tools']);
        $this->assertSame('create_node', $decoded['result']['tools'][0]['name']);
        $this->assertArrayHasKey('inputSchema', $decoded['result']['tools'][0]);
    }

    #[Test]
    public function toolsCallExecutesToolAndReturnsResult(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);

        $tool = $this->makeTool('read_node');
        $this->registry->method('getTools')->willReturn([$tool]);
        $this->registry->method('getTool')
            ->with('read_node')
            ->willReturn($tool);

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
        $response = $this->dispatch($endpoint, 'POST', \json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'read_node',
                'arguments' => ['id' => 42],
            ],
        ]), 'Bearer valid-token');

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
        $response = $this->dispatch($endpoint, 'POST', \json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'nonexistent_tool',
                'arguments' => [],
            ],
        ]), 'Bearer valid-token');

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertArrayHasKey('error', $decoded);
    }

    #[Test]
    public function invalidJsonReturnsParseError(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);

        $endpoint = $this->createEndpoint();
        $response = $this->dispatch($endpoint, 'POST', '{invalid json', 'Bearer valid-token');

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertSame(-32700, $decoded['error']['code']);
    }

    #[Test]
    public function missingMethodFieldReturnsInvalidRequest(): void
    {
        $this->auth->method('authenticate')->willReturn($this->account);

        $endpoint = $this->createEndpoint();
        $response = $this->dispatch($endpoint, 'POST', \json_encode(['jsonrpc' => '2.0', 'id' => 1]), 'Bearer valid-token');

        $this->assertSame(200, $response->statusCode);

        $decoded = \json_decode($response->body, true);
        $this->assertSame(-32600, $decoded['error']['code']);
    }

    #[Test]
    public function responseContentTypeIsJson(): void
    {
        $this->auth->method('authenticate')->willReturn(null);

        $endpoint = $this->createEndpoint();
        $response = $this->dispatch($endpoint, 'POST', '{}', null);

        $this->assertSame('application/json', $response->contentType);
    }
}

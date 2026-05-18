<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\PhaseN\AgentRuntime;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Mcp\McpClientToolSource;
use Waaseyaa\AI\Agent\Mcp\StreamableHttpMcpClient;
use Waaseyaa\AI\Agent\ToolRegistry;
use Waaseyaa\Config\Schema\Ai\McpServersConfig;
use Waaseyaa\Tests\Integration\PhaseN\AgentRuntime\Fixture\InMemoryConfigStorage;
use Waaseyaa\Tests\Integration\PhaseN\AgentRuntime\Fixture\StubMcpServerHttpClient;

/**
 * End-to-end test for {@see McpClientToolSource} against an in-process
 * stub MCP server that speaks proper JSON-RPC 2.0 over the
 * {@see \Waaseyaa\HttpClient\HttpClientInterface} transport.
 *
 * The stub server defines two tools (`echo`, `add`). After
 * `bootstrap()` we assert both appear in the registry under the
 * configured `stub` alias, and that calling the registered executor
 * round-trips the JSON-RPC payload.
 *
 * Per the WP-07 implementer prompt: WP-04's `AgentRunService::runInline()`
 * is not yet present on lane-g, so this test exercises the registered
 * executor directly via {@see ToolRegistry::execute()} — the seam WP-04
 * will later wrap.
 */
#[CoversNothing]
final class McpClientToolSourceTest extends TestCase
{
    #[Test]
    public function bootstrapRegistersRemoteToolsUnderAlias(): void
    {
        $http = new StubMcpServerHttpClient();
        $http->registerTool('echo', 'Echo back input', [
            'type' => 'object',
            'properties' => ['msg' => ['type' => 'string']],
            'required' => ['msg'],
        ], static fn(array $args): array => [
            'isError' => false,
            'content' => [['type' => 'text', 'text' => (string) ($args['msg'] ?? '')]],
        ]);
        $http->registerTool('add', 'Add two numbers', [
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number'],
            ],
            'required' => ['a', 'b'],
        ], static fn(array $args): array => [
            'isError' => false,
            'content' => [['type' => 'text', 'text' => (string) (((float) ($args['a'] ?? 0)) + ((float) ($args['b'] ?? 0)))]],
        ]);

        $configStorage = new InMemoryConfigStorage();
        $configStorage->write(McpServersConfig::CONFIG_NAME, [
            McpServersConfig::ITEMS_KEY => [
                [
                    'alias' => 'stub',
                    'url' => 'https://stub.invalid/mcp',
                    'auth_header_env_var' => '',
                    'enabled' => true,
                    'capability_prefix' => 'tool.mcp.stub',
                ],
            ],
        ]);

        $registry = new ToolRegistry();
        $client = new StreamableHttpMcpClient($http);
        $source = new McpClientToolSource($client, $registry, $configStorage);

        $source->bootstrap();

        self::assertTrue($registry->has('stub.echo'), 'echo tool registered under alias');
        self::assertTrue($registry->has('stub.add'), 'add tool registered under alias');

        $echo = $registry->getTool('stub.echo');
        self::assertNotNull($echo);
        self::assertSame('Echo back input', $echo->description);
        self::assertSame([
            'alias' => 'stub',
            'capability' => 'tool.mcp.stub.echo',
            'category' => 'mcp.stub',
            'destructive' => true,
            'dry_run_supported' => false,
        ], $echo->inputSchema['x-mcp-source']);
    }

    #[Test]
    public function executorRoundTripsThroughStubServer(): void
    {
        $http = new StubMcpServerHttpClient();
        $http->registerTool('echo', 'Echo back input', [
            'type' => 'object',
            'properties' => ['msg' => ['type' => 'string']],
        ], static fn(array $args): array => [
            'isError' => false,
            'content' => [['type' => 'text', 'text' => 'echoed:' . (string) ($args['msg'] ?? '')]],
        ]);

        $configStorage = new InMemoryConfigStorage();
        $configStorage->write(McpServersConfig::CONFIG_NAME, [
            McpServersConfig::ITEMS_KEY => [
                [
                    'alias' => 'stub',
                    'url' => 'https://stub.invalid/mcp',
                    'auth_header_env_var' => '',
                    'enabled' => true,
                    'capability_prefix' => 'tool.mcp.stub',
                ],
            ],
        ]);

        $registry = new ToolRegistry();
        $source = new McpClientToolSource(new StreamableHttpMcpClient($http), $registry, $configStorage);
        $source->bootstrap();

        $result = $registry->execute('stub.echo', ['msg' => 'hello']);

        self::assertSame('echoed:hello', $result['content'][0]['text']);
        self::assertArrayNotHasKey('isError', $result);
    }

    #[Test]
    public function unavailableServerDegradesGracefully(): void
    {
        $http = new StubMcpServerHttpClient(serverAvailable: false);

        $configStorage = new InMemoryConfigStorage();
        $configStorage->write(McpServersConfig::CONFIG_NAME, [
            McpServersConfig::ITEMS_KEY => [
                [
                    'alias' => 'down',
                    'url' => 'https://down.invalid/mcp',
                    'auth_header_env_var' => '',
                    'enabled' => true,
                    'capability_prefix' => 'tool.mcp.down',
                ],
            ],
        ]);

        $registry = new ToolRegistry();
        $source = new McpClientToolSource(new StreamableHttpMcpClient($http), $registry, $configStorage);

        // Must not throw — graceful degrade per FR-021 edge case.
        $source->bootstrap();

        self::assertSame([], $registry->getTools());
    }

    #[Test]
    public function disabledServerIsSkipped(): void
    {
        $http = new StubMcpServerHttpClient();
        $http->registerTool('echo', 'd', ['type' => 'object'], static fn(): array => ['isError' => false, 'content' => []]);

        $configStorage = new InMemoryConfigStorage();
        $configStorage->write(McpServersConfig::CONFIG_NAME, [
            McpServersConfig::ITEMS_KEY => [
                [
                    'alias' => 'stub',
                    'url' => 'https://stub.invalid/mcp',
                    'auth_header_env_var' => '',
                    'enabled' => false,
                    'capability_prefix' => 'tool.mcp.stub',
                ],
            ],
        ]);

        $registry = new ToolRegistry();
        $source = new McpClientToolSource(new StreamableHttpMcpClient($http), $registry, $configStorage);
        $source->bootstrap();

        self::assertFalse($registry->has('stub.echo'));
    }
}

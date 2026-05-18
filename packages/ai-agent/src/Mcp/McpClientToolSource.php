<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Mcp;

use Waaseyaa\AI\Agent\ToolRegistryInterface;
use Waaseyaa\AI\Schema\Mcp\McpToolDefinition;
use Waaseyaa\Config\Schema\Ai\McpServersConfig;
use Waaseyaa\Config\StorageInterface as ConfigStorageInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * Discovers tools on every enabled remote MCP server and registers each
 * one in the local tool catalogue with a server-aliased name.
 *
 * Naming contract (per WP-07 prompt + data-model):
 *
 * - **Tool name**       `"{$alias}.{$descriptor->name}"`         (e.g. `github.create_issue`)
 * - **Capability**      `"{$capabilityPrefix}.{$descriptor->name}"` (e.g. `tool.mcp.github.create_issue`)
 * - **Category** (hint) `"mcp.{$alias}"`
 *
 * Per-server failures are caught and logged; the rest of the catalogue
 * is built unaffected ("graceful degrade" per spec edge case).
 *
 * Each registered executor reads the auth header from
 * `getenv($auth_header_env_var)` at call time — never at config-load
 * time — per C-010.
 *
 * @api
 */
final class McpClientToolSource
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly StreamableHttpMcpClient $client,
        private readonly ToolRegistryInterface $registry,
        private readonly ConfigStorageInterface $configStorage,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Walk the configured remote MCP servers and register their tools.
     */
    public function bootstrap(): void
    {
        $raw = $this->configStorage->read(McpServersConfig::CONFIG_NAME);
        $rows = McpServersConfig::normalise($raw);

        $duplicates = McpServersConfig::hasDuplicateAliases($rows);
        if ($duplicates !== []) {
            $this->logger->warning(
                'Duplicate MCP server aliases detected; later rows shadow earlier ones',
                ['duplicate_aliases' => $duplicates],
            );
        }

        foreach ($rows as $row) {
            if (!$row['enabled']) {
                continue;
            }
            $this->bootstrapServer($row);
        }
    }

    /**
     * Return the list of capabilities derived from the current configuration,
     * suitable for surfacing via {@see McpCapabilitiesSource}.
     *
     * @return list<string>
     */
    public function discoveredCapabilities(): array
    {
        $raw = $this->configStorage->read(McpServersConfig::CONFIG_NAME);
        $rows = McpServersConfig::normalise($raw);

        $caps = [];
        foreach ($rows as $row) {
            if (!$row['enabled']) {
                continue;
            }
            try {
                $descriptors = $this->client->listTools($row['url'], $this->resolveAuthHeader($row));
            } catch (McpServerUnavailableException $e) {
                $this->logger->warning('MCP server unavailable during capability enumeration', [
                    'alias' => $row['alias'],
                    'url' => $row['url'],
                    'message' => $e->getMessage(),
                ]);
                continue;
            }
            foreach ($descriptors as $descriptor) {
                $caps[] = sprintf('%s.%s', $row['capability_prefix'], $descriptor->name);
            }
        }

        return array_values(array_unique($caps));
    }

    /**
     * @param array{
     *     alias: string,
     *     url: string,
     *     auth_header_env_var: string,
     *     enabled: bool,
     *     capability_prefix: string,
     * } $row
     */
    private function bootstrapServer(array $row): void
    {
        try {
            $authHeader = $this->resolveAuthHeader($row);
            $this->client->initialize($row['url'], $authHeader);
            $descriptors = $this->client->listTools($row['url'], $authHeader);
        } catch (McpServerUnavailableException $e) {
            $this->logger->warning('Skipping MCP server: unavailable at boot', [
                'alias' => $row['alias'],
                'url' => $row['url'],
                'message' => $e->getMessage(),
            ]);

            return;
        } catch (\Throwable $e) {
            $this->logger->warning('Skipping MCP server: unexpected error', [
                'alias' => $row['alias'],
                'url' => $row['url'],
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($descriptors as $descriptor) {
            $this->registerDescriptor($row, $descriptor);
        }
    }

    /**
     * @param array{
     *     alias: string,
     *     url: string,
     *     auth_header_env_var: string,
     *     enabled: bool,
     *     capability_prefix: string,
     * } $row
     */
    private function registerDescriptor(array $row, McpRemoteToolDescriptor $descriptor): void
    {
        $localName = sprintf('%s.%s', $row['alias'], $descriptor->name);
        $capability = sprintf('%s.%s', $row['capability_prefix'], $descriptor->name);

        // Conservative default: remote tools are destructive unless the
        // server opts out via a `destructive: false` hint in its descriptor.
        $destructive = true;
        if (array_key_exists('destructive', $descriptor->metadata)) {
            $destructive = (bool) $descriptor->metadata['destructive'];
        }

        $description = $descriptor->description !== ''
            ? $descriptor->description
            : sprintf('Remote MCP tool %s on server %s', $descriptor->name, $row['alias']);

        // Surface routing/governance metadata via inputSchema annotation so
        // it survives transport through the legacy McpToolDefinition shape.
        // Strict consumers will ignore `x-*` keys; richer consumers (once
        // WP01's AgentTool lands) can reify them into real fields.
        $annotated = $descriptor->inputSchema;
        $annotated['x-mcp-source'] = [
            'alias' => $row['alias'],
            'capability' => $capability,
            'category' => sprintf('mcp.%s', $row['alias']),
            'destructive' => $destructive,
            'dry_run_supported' => false,
        ];

        $definition = new McpToolDefinition(
            name: $localName,
            description: $description,
            inputSchema: $annotated,
        );

        $url = $row['url'];
        $envVar = $row['auth_header_env_var'];
        $remoteName = $descriptor->name;
        $client = $this->client;
        $logger = $this->logger;

        $executor = static function (array $arguments) use ($client, $url, $envVar, $remoteName, $logger): array {
            $authHeader = null;
            if ($envVar !== '') {
                $envValue = getenv($envVar);
                $authHeader = ($envValue === false || $envValue === '') ? null : $envValue;
            }
            try {
                $result = $client->callTool($url, $authHeader, $remoteName, $arguments);
            } catch (McpServerUnavailableException $e) {
                $logger->warning('Remote MCP tool call failed: server unavailable', [
                    'url' => $url,
                    'tool' => $remoteName,
                    'message' => $e->getMessage(),
                ]);

                return [
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode(
                            ['error' => 'mcp_server_unavailable', 'detail' => $e->getMessage()],
                            JSON_THROW_ON_ERROR,
                        ),
                    ]],
                    'isError' => true,
                ];
            }

            return $result->toRegistryShape();
        };

        $this->registry->register($definition, $executor);
    }

    /**
     * @param array{auth_header_env_var: string} $row
     */
    private function resolveAuthHeader(array $row): ?string
    {
        if ($row['auth_header_env_var'] === '') {
            return null;
        }
        $value = getenv($row['auth_header_env_var']);

        return ($value === false || $value === '') ? null : $value;
    }
}

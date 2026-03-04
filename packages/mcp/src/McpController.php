<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\AI\Vector\EmbeddingProviderInterface;
use Waaseyaa\AI\Vector\EmbeddingStorageInterface;
use Waaseyaa\AI\Vector\SearchController;
use Waaseyaa\Api\JsonApiController;
use Waaseyaa\Api\ResourceSerializer;
use Waaseyaa\Entity\EntityTypeManagerInterface;

final class McpController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly ResourceSerializer $serializer,
        private readonly EntityAccessHandler $accessHandler,
        private readonly AccountInterface $account,
        private readonly EmbeddingStorageInterface $embeddingStorage,
        private readonly ?EmbeddingProviderInterface $embeddingProvider = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'server' => [
                'name' => 'Waaseyaa MCP',
                'version' => '0.4.0',
            ],
            'tools' => [
                ['name' => 'search_teachings', 'description' => 'Semantic search for teachings'],
                ['name' => 'get_entity', 'description' => 'Fetch a single entity by type and ID'],
                ['name' => 'list_entity_types', 'description' => 'List available entity types and schemas'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $rpc
     * @return array<string, mixed>
     */
    public function handleRpc(array $rpc): array
    {
        $id = $rpc['id'] ?? null;
        $method = is_string($rpc['method'] ?? null) ? $rpc['method'] : '';
        $params = is_array($rpc['params'] ?? null) ? $rpc['params'] : [];

        return match ($method) {
            'tools/list' => $this->result($id, ['tools' => $this->manifest()['tools']]),
            'tools/call' => $this->handleToolCall($id, $params),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleToolCall(mixed $id, array $params): array
    {
        $tool = is_string($params['name'] ?? null) ? $params['name'] : '';
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if ($tool === '') {
            return $this->error($id, -32602, 'Missing tool name.');
        }

        try {
            $result = match ($tool) {
                'search_teachings' => $this->toolSearchTeachings($arguments),
                'get_entity' => $this->toolGetEntity($arguments),
                'list_entity_types' => $this->toolListEntityTypes(),
                default => null,
            };
        } catch (\Throwable $e) {
            return $this->error($id, -32000, $e->getMessage());
        }

        if ($result === null) {
            return $this->error($id, -32602, "Unknown tool: {$tool}");
        }

        return $this->result($id, [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            ]],
        ]);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function toolSearchTeachings(array $arguments): array
    {
        $query = is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';
        $entityType = is_string($arguments['type'] ?? null) ? trim($arguments['type']) : 'node';
        $limit = is_numeric($arguments['limit'] ?? null) ? (int) $arguments['limit'] : 10;

        $controller = new SearchController(
            entityTypeManager: $this->entityTypeManager,
            serializer: $this->serializer,
            embeddingStorage: $this->embeddingStorage,
            embeddingProvider: $this->embeddingProvider,
            accessHandler: $this->accessHandler,
            account: $this->account,
        );

        return $controller->search($query, $entityType, $limit)->toArray();
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function toolGetEntity(array $arguments): array
    {
        $entityType = is_string($arguments['type'] ?? null) ? trim($arguments['type']) : '';
        $id = $arguments['id'] ?? null;

        $controller = new JsonApiController(
            entityTypeManager: $this->entityTypeManager,
            serializer: $this->serializer,
            accessHandler: $this->accessHandler,
            account: $this->account,
        );

        return $controller->show($entityType, is_numeric((string) $id) ? (int) $id : (string) $id)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function toolListEntityTypes(): array
    {
        $types = [];
        foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
            $types[] = [
                'id' => $id,
                'label' => $definition->getLabel(),
                'keys' => $definition->getKeys(),
                'fields' => $definition->getFieldDefinitions(),
            ];
        }

        return ['data' => $types];
    }

    /**
     * @return array<string, mixed>
     */
    private function result(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}

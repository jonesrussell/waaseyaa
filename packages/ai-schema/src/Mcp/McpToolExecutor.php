<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Schema\Mcp;

use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\EntityValues;
use Waaseyaa\Entity\FieldableInterface;

/**
 * Executes MCP tool calls against the Waaseyaa entity system.
 *
 * Parses tool names to determine the operation and entity type,
 * then delegates to the appropriate entity storage methods.
 * Results are returned in MCP-compliant format.
 */
final class McpToolExecutor
{
    private const OPERATIONS = ['create', 'read', 'update', 'delete', 'query'];

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    /**
     * Execute an MCP tool call.
     *
     * @param string $toolName The tool name (e.g. "create_node", "read_user").
     * @param array<string, mixed> $arguments The tool input arguments.
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    public function execute(string $toolName, array $arguments): array
    {
        try {
            [$operation, $entityTypeId] = $this->parseToolName($toolName);

            if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
                return $this->errorResult("Unknown entity type: {$entityTypeId}");
            }

            return match ($operation) {
                'create' => $this->executeCreate($entityTypeId, $arguments),
                'read' => $this->executeRead($entityTypeId, $arguments),
                'update' => $this->executeUpdate($entityTypeId, $arguments),
                'delete' => $this->executeDelete($entityTypeId, $arguments),
                'query' => $this->executeQuery($entityTypeId, $arguments),
            };
        } catch (\Throwable $e) {
            return $this->errorResult($e->getMessage());
        }
    }

    /**
     * Parse a tool name into [operation, entityTypeId].
     *
     * @return array{0: string, 1: string}
     */
    private function parseToolName(string $toolName): array
    {
        foreach (self::OPERATIONS as $operation) {
            $prefix = $operation . '_';
            if (\str_starts_with($toolName, $prefix)) {
                $entityTypeId = \substr($toolName, \strlen($prefix));
                if ($entityTypeId !== '') {
                    return [$operation, $entityTypeId];
                }
            }
        }

        throw new \InvalidArgumentException("Unknown tool: {$toolName}");
    }

    /**
     * @return array{content: array<int, array{type: string, text: string}>}
     */
    private function executeCreate(string $entityTypeId, array $arguments): array
    {
        $attributes = $arguments['attributes'] ?? [];
        $storage = $this->entityTypeManager->getStorage($entityTypeId);
        $entity = $storage->create($attributes);
        $storage->save($entity);

        return $this->successResult([
            'operation' => 'create',
            'entity_type' => $entityTypeId,
            'id' => $entity->id(),
            'data' => EntityValues::toCastAwareMap($entity),
        ]);
    }

    /**
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    private function executeRead(string $entityTypeId, array $arguments): array
    {
        if (!isset($arguments['id'])) {
            return $this->errorResult('Missing required argument: id');
        }

        $storage = $this->entityTypeManager->getStorage($entityTypeId);
        $entity = $storage->load($arguments['id']);

        if ($entity === null) {
            return $this->errorResult("Entity {$entityTypeId} with ID {$arguments['id']} not found.");
        }

        return $this->successResult([
            'operation' => 'read',
            'entity_type' => $entityTypeId,
            'id' => $entity->id(),
            'data' => EntityValues::toCastAwareMap($entity),
        ]);
    }

    /**
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    private function executeUpdate(string $entityTypeId, array $arguments): array
    {
        if (!isset($arguments['id'])) {
            return $this->errorResult('Missing required argument: id');
        }

        if (!isset($arguments['attributes'])) {
            return $this->errorResult('Missing required argument: attributes');
        }

        $storage = $this->entityTypeManager->getStorage($entityTypeId);
        $entity = $storage->load($arguments['id']);

        if ($entity === null) {
            return $this->errorResult("Entity {$entityTypeId} with ID {$arguments['id']} not found.");
        }

        if (!$entity instanceof FieldableInterface) {
            return $this->errorResult(
                "Entity type {$entityTypeId} does not support field updates.",
            );
        }

        foreach ($arguments['attributes'] as $field => $value) {
            $entity->set($field, $value);
        }

        $storage->save($entity);

        return $this->successResult([
            'operation' => 'update',
            'entity_type' => $entityTypeId,
            'id' => $entity->id(),
            'data' => EntityValues::toCastAwareMap($entity),
        ]);
    }

    /**
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    private function executeDelete(string $entityTypeId, array $arguments): array
    {
        if (!isset($arguments['id'])) {
            return $this->errorResult('Missing required argument: id');
        }

        $storage = $this->entityTypeManager->getStorage($entityTypeId);
        $entity = $storage->load($arguments['id']);

        if ($entity === null) {
            return $this->errorResult("Entity {$entityTypeId} with ID {$arguments['id']} not found.");
        }

        $storage->delete([$entity]);

        return $this->successResult([
            'operation' => 'delete',
            'entity_type' => $entityTypeId,
            'id' => $arguments['id'],
        ]);
    }

    /**
     * @return array{content: array<int, array{type: string, text: string}>}
     */
    private function executeQuery(string $entityTypeId, array $arguments): array
    {
        $storage = $this->entityTypeManager->getStorage($entityTypeId);
        $query = $storage->getQuery();

        // Disable access checking for MCP tool calls (AI agent context).
        $query->accessCheck(false);

        // Apply filters.
        if (isset($arguments['filters']) && \is_array($arguments['filters'])) {
            foreach ($arguments['filters'] as $filter) {
                if (isset($filter['field'], $filter['value'])) {
                    $operator = $filter['operator'] ?? '=';
                    $query->condition($filter['field'], $filter['value'], $operator);
                }
            }
        }

        // Apply sorting.
        if (isset($arguments['sort']) && \is_string($arguments['sort']) && $arguments['sort'] !== '') {
            $sortField = $arguments['sort'];
            $direction = 'ASC';

            if (\str_starts_with($sortField, '-')) {
                $sortField = \substr($sortField, 1);
                $direction = 'DESC';
            }

            $query->sort($sortField, $direction);
        }

        // Apply pagination.
        $limit = isset($arguments['limit']) ? (int) $arguments['limit'] : 50;
        $offset = isset($arguments['offset']) ? (int) $arguments['offset'] : 0;
        $query->range($offset, $limit);

        $ids = $query->execute();

        // Load the entities and convert to arrays.
        $results = [];
        if ($ids !== []) {
            $entities = $storage->loadMultiple($ids);
            foreach ($entities as $entity) {
                $results[] = [
                    'id' => $entity->id(),
                    'data' => EntityValues::toCastAwareMap($entity),
                ];
            }
        }

        return $this->successResult([
            'operation' => 'query',
            'entity_type' => $entityTypeId,
            'count' => \count($results),
            'results' => $results,
        ]);
    }

    /**
     * Build a successful MCP result.
     *
     * @return array{content: array<int, array{type: string, text: string}>}
     */
    private function successResult(mixed $data): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => \json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
                ],
            ],
        ];
    }

    /**
     * Build an error MCP result.
     *
     * @return array{content: array<int, array{type: string, text: string}>, isError: bool}
     */
    private function errorResult(string $message): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => \json_encode(['error' => $message], \JSON_THROW_ON_ERROR),
                ],
            ],
            'isError' => true,
        ];
    }
}

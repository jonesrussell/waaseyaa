<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp\Tools;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Api\ResourceSerializer;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Relationship\RelationshipTraversalService;

final class TraversalTools extends McpTool
{
    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        ResourceSerializer $serializer,
        EntityAccessHandler $accessHandler,
        AccountInterface $account,
        private readonly ?RelationshipTraversalService $relationshipTraversal = null,
    ) {
        parent::__construct($entityTypeManager, $serializer, $accessHandler, $account);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function traverse(array $arguments): array
    {
        $parsed = $this->parseTraversalArguments($arguments);
        $this->assertTraversalSourceVisible($parsed['entity_type'], $parsed['entity_id']);
        $rows = $this->collectTraversalRows($parsed);

        $data = [];
        foreach ($rows as $row) {
            $resource = $this->serializer
                ->serialize($row['relationship'], $this->accessHandler, $this->account)
                ->toArray();
            $resource['meta'] = [
                'source' => [
                    'type' => $parsed['entity_type'],
                    'id' => $parsed['entity_id'],
                ],
                'related' => [
                    'type' => $row['related_entity_type'],
                    'id' => $row['related_entity_id'],
                ],
                'direction' => $row['direction'],
                'inverse' => $row['inverse'],
            ];
            $data[] = $resource;
        }

        return [
            'data' => $data,
            'meta' => [
                'filters' => [
                    'entity_type' => $parsed['entity_type'],
                    'entity_id' => $parsed['entity_id'],
                    'direction' => $parsed['direction'],
                    'status' => $parsed['status'],
                    'relationship_types' => $parsed['relationship_types'],
                    'at' => $parsed['at'],
                    'limit' => $parsed['limit'],
                ],
                'count' => count($data),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function getRelated(array $arguments): array
    {
        $parsed = $this->parseTraversalArguments($arguments);
        $this->assertTraversalSourceVisible($parsed['entity_type'], $parsed['entity_id']);
        $includeRelationships = filter_var($arguments['include_relationships'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $rows = $this->collectTraversalRows($parsed);

        /** @var array<string, array<string, mixed>> $entitiesByKey */
        $entitiesByKey = [];
        $relationshipData = [];
        foreach ($rows as $row) {
            $relatedType = $row['related_entity_type'];
            $relatedId = $row['related_entity_id'];

            if (!$this->entityTypeManager->hasDefinition($relatedType)) {
                continue;
            }

            $storage = $this->entityTypeManager->getStorage($relatedType);
            $resolvedId = ctype_digit($relatedId) ? (int) $relatedId : $relatedId;
            $entity = $storage->load($resolvedId);
            if ($entity === null) {
                continue;
            }

            $access = $this->accessHandler->check($entity, 'view', $this->account);
            if (!$access->isAllowed()) {
                continue;
            }

            $serialized = $this->serializer->serialize($entity, $this->accessHandler, $this->account)->toArray();
            $serialized['meta'] = [
                'via_relationship_id' => (string) $row['relationship']->id(),
                'via_relationship_type' => (string) ($row['relationship']->get('relationship_type') ?? ''),
                'direction' => $row['direction'],
                'inverse' => $row['inverse'],
            ];

            $resourceKey = $serialized['type'] . ':' . $serialized['id'];
            if (!isset($entitiesByKey[$resourceKey])) {
                $entitiesByKey[$resourceKey] = $serialized;
            }

            if ($includeRelationships) {
                $relationshipData[] = $this->serializer
                    ->serialize($row['relationship'], $this->accessHandler, $this->account)
                    ->toArray();
            }
        }

        $payload = [
            'data' => array_values($entitiesByKey),
            'meta' => [
                'filters' => [
                    'entity_type' => $parsed['entity_type'],
                    'entity_id' => $parsed['entity_id'],
                    'direction' => $parsed['direction'],
                    'status' => $parsed['status'],
                    'relationship_types' => $parsed['relationship_types'],
                    'at' => $parsed['at'],
                    'limit' => $parsed['limit'],
                ],
                'count' => count($entitiesByKey),
            ],
        ];

        if ($includeRelationships) {
            $payload['relationships'] = $relationshipData;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function knowledgeGraph(array $arguments): array
    {
        $parsed = $this->parseTraversalArguments($arguments);
        $this->assertTraversalSourceVisible($parsed['entity_type'], $parsed['entity_id']);

        if ($this->relationshipTraversal !== null) {
            $graph = $this->relationshipTraversal->browse($parsed['entity_type'], $parsed['entity_id'], [
                'relationship_types' => $parsed['relationship_types'],
                'status' => $parsed['status'],
                'at' => $parsed['at'],
                'limit' => $parsed['limit'],
            ]);
        } else {
            $rows = $this->collectTraversalRows(array_merge($parsed, ['direction' => 'both']));
            $graph = [
                'source' => [
                    'type' => $parsed['entity_type'],
                    'id' => $parsed['entity_id'],
                ],
                'outbound' => [],
                'inbound' => [],
                'counts' => ['outbound' => 0, 'inbound' => 0, 'total' => 0],
            ];

            foreach ($rows as $row) {
                $edge = [
                    'relationship_id' => (string) $row['relationship']->id(),
                    'relationship_type' => (string) ($row['relationship']->get('relationship_type') ?? ''),
                    'direction' => $row['direction'],
                    'inverse' => (bool) ($row['inverse'] ?? false),
                    'directionality' => (string) ($row['relationship']->get('directionality') ?? 'directed'),
                    'related_entity_type' => $row['related_entity_type'],
                    'related_entity_id' => $row['related_entity_id'],
                    'related_entity_label' => $row['related_entity_type'] . ':' . $row['related_entity_id'],
                    'related_entity_path' => '/' . $row['related_entity_type'] . '/' . $row['related_entity_id'],
                    'status' => (int) ($row['relationship']->get('status') ?? 0),
                    'weight' => is_numeric($row['relationship']->get('weight')) ? (float) $row['relationship']->get('weight') : null,
                    'confidence' => is_numeric($row['relationship']->get('confidence')) ? (float) $row['relationship']->get('confidence') : null,
                    'start_date' => $this->normalizeTemporal($row['relationship']->get('start_date')),
                    'end_date' => $this->normalizeTemporal($row['relationship']->get('end_date')),
                ];

                if ($row['direction'] === 'inbound') {
                    $graph['inbound'][] = $edge;
                } else {
                    $graph['outbound'][] = $edge;
                }
            }

            $graph['counts']['outbound'] = count($graph['outbound']);
            $graph['counts']['inbound'] = count($graph['inbound']);
            $graph['counts']['total'] = $graph['counts']['outbound'] + $graph['counts']['inbound'];
        }

        return [
            'data' => $graph,
            'meta' => [
                'filters' => [
                    'entity_type' => $parsed['entity_type'],
                    'entity_id' => $parsed['entity_id'],
                    'status' => $parsed['status'],
                    'relationship_types' => $parsed['relationship_types'],
                    'at' => $parsed['at'],
                    'limit' => $parsed['limit'],
                ],
                'count' => (int) ($graph['counts']['total'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{
     *   entity_type: string,
     *   entity_id: string,
     *   direction: string,
     *   status: string,
     *   relationship_types: list<string>,
     *   at: ?int,
     *   limit: int
     * }
     */
    public function parseTraversalArguments(array $arguments): array
    {
        $entityType = is_string($arguments['type'] ?? null) ? trim($arguments['type']) : '';
        $entityIdRaw = $arguments['id'] ?? null;
        $entityId = is_scalar($entityIdRaw) ? trim((string) $entityIdRaw) : '';
        if ($entityType === '' || $entityId === '') {
            throw new \InvalidArgumentException('Traversal requires non-empty "type" and "id" arguments.');
        }
        $entityType = strtolower($entityType);
        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(sprintf('Unknown traversal entity type: "%s".', $entityType));
        }

        $direction = is_string($arguments['direction'] ?? null) ? strtolower(trim($arguments['direction'])) : 'both';
        if (!in_array($direction, ['outbound', 'inbound', 'both'], true)) {
            throw new \InvalidArgumentException('Traversal "direction" must be one of: outbound, inbound, both.');
        }

        $status = is_string($arguments['status'] ?? null) ? strtolower(trim($arguments['status'])) : 'published';
        if (!in_array($status, ['published', 'unpublished', 'all'], true)) {
            throw new \InvalidArgumentException('Traversal "status" must be one of: published, unpublished, all.');
        }

        $relationshipTypes = [];
        $rawRelationshipTypes = $arguments['relationship_types'] ?? [];
        if (is_array($rawRelationshipTypes)) {
            foreach ($rawRelationshipTypes as $value) {
                if (!is_string($value)) {
                    continue;
                }
                $normalized = trim(strtolower($value));
                if ($normalized === '') {
                    continue;
                }
                $relationshipTypes[] = $normalized;
            }
        }
        $relationshipTypes = array_values(array_unique($relationshipTypes));

        $at = null;
        if (array_key_exists('at', $arguments)) {
            $at = $this->normalizeTemporal($arguments['at']);
            if ($at === null) {
                throw new \InvalidArgumentException('Traversal "at" must be a unix timestamp or parseable datetime string.');
            }
        }

        $limit = is_numeric($arguments['limit'] ?? null) ? (int) $arguments['limit'] : 25;
        $limit = max(1, min(100, $limit));

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'direction' => $direction,
            'status' => $status,
            'relationship_types' => $relationshipTypes,
            'at' => $at,
            'limit' => $limit,
        ];
    }
}

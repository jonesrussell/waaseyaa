<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Vector;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Api\JsonApiDocument;
use Waaseyaa\Api\JsonApiError;
use Waaseyaa\Api\ResourceSerializer;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class SearchController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly ResourceSerializer $serializer,
        private readonly EmbeddingStorageInterface $embeddingStorage,
        private readonly ?EmbeddingProviderInterface $embeddingProvider = null,
        private readonly ?EntityAccessHandler $accessHandler = null,
        private readonly ?AccountInterface $account = null,
    ) {}

    public function search(string $query, string $entityTypeId, int $limit = 10): JsonApiDocument
    {
        if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
            return JsonApiDocument::fromErrors(
                [JsonApiError::notFound("Unknown entity type: {$entityTypeId}.")],
                statusCode: 404,
            );
        }

        $query = trim($query);
        if ($query === '') {
            return JsonApiDocument::fromCollection([], meta: ['query' => '', 'mode' => 'empty']);
        }

        $limit = max(1, min(100, $limit));
        $storage = $this->entityTypeManager->getStorage($entityTypeId);
        $mode = $this->embeddingProvider !== null ? 'semantic' : 'keyword';
        $fallbackReason = null;
        $requestedMode = $mode;

        $semanticScores = [];
        if ($this->embeddingProvider !== null) {
            $semanticResult = $this->semanticSearchIds($query, $entityTypeId, $limit);
            if ($semanticResult['fallback_reason'] !== null) {
                $mode = 'keyword';
                $fallbackReason = $semanticResult['fallback_reason'];
                $ids = $this->keywordSearchIds($storage, $query, $limit);
            } else {
                $ids = $semanticResult['ids'];
                $semanticScores = $semanticResult['scores'];
            }
        } else {
            $ids = $this->keywordSearchIds($storage, $query, $limit);
        }

        $graphRerankApplied = false;
        if ($mode === 'semantic' && $ids !== []) {
            $graphRerank = $this->rerankWithRelationshipContext($entityTypeId, $ids, $semanticScores);
            $ids = $graphRerank['ids'];
            $graphRerankApplied = $graphRerank['applied'];
        }

        $entities = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $orderedEntities = [];
        foreach ($ids as $id) {
            if (isset($entities[$id])) {
                $orderedEntities[] = $entities[$id];
            }
        }

        if ($this->accessHandler !== null && $this->account !== null) {
            $orderedEntities = array_values(array_filter(
                $orderedEntities,
                fn($entity) => $this->accessHandler->check($entity, 'view', $this->account)->isAllowed(),
            ));
        }

        $resources = $this->serializer->serializeCollection($orderedEntities, $this->accessHandler, $this->account);

        $meta = [
            'query' => $query,
            'type' => $entityTypeId,
            'limit' => $limit,
            'mode' => $mode,
        ];
        if ($fallbackReason !== null) {
            $meta['requested_mode'] = $requestedMode;
            $meta['fallback_reason'] = $fallbackReason;
        }
        if ($graphRerankApplied) {
            $meta['ranking'] = 'semantic+graph_context';
        }

        return JsonApiDocument::fromCollection($resources, meta: $meta);
    }

    /**
     * @param array<int|string> $ids
     * @param array<string, float> $semanticScores
     * @return array{ids: array<int|string>, applied: bool}
     */
    private function rerankWithRelationshipContext(string $entityTypeId, array $ids, array $semanticScores): array
    {
        if (!$this->entityTypeManager->hasDefinition('relationship')) {
            return ['ids' => $ids, 'applied' => false];
        }

        $baseOrder = [];
        foreach ($ids as $index => $id) {
            $key = (string) $id;
            $baseOrder[$key] = $index;
        }

        try {
            $relationshipStorage = $this->entityTypeManager->getStorage('relationship');
            $relationshipIds = $relationshipStorage->getQuery()->accessCheck(false)->execute();
            if ($relationshipIds === []) {
                return ['ids' => $ids, 'applied' => false];
            }

            $relationshipEntities = $relationshipStorage->loadMultiple($relationshipIds);
        } catch (\Throwable) {
            return ['ids' => $ids, 'applied' => false];
        }

        $relationshipScores = [];
        foreach ($ids as $id) {
            $relationshipScores[(string) $id] = 0;
        }

        foreach ($relationshipEntities as $relationship) {
            $values = $relationship->toArray();
            if ((int) ($values['status'] ?? 0) !== 1) {
                continue;
            }

            $fromType = (string) ($values['from_entity_type'] ?? '');
            $toType = (string) ($values['to_entity_type'] ?? '');
            $fromId = (string) ($values['from_entity_id'] ?? '');
            $toId = (string) ($values['to_entity_id'] ?? '');

            if ($fromType === $entityTypeId && isset($relationshipScores[$fromId])) {
                $relationshipScores[$fromId] += 1;
            }
            if ($toType === $entityTypeId && isset($relationshipScores[$toId])) {
                $relationshipScores[$toId] += 1;
            }
        }

        $combinedScores = [];
        foreach ($ids as $id) {
            $key = (string) $id;
            $semantic = $semanticScores[$key] ?? (1.0 - (($baseOrder[$key] ?? 0) * 0.0001));
            $combinedScores[$key] = $semantic + (($relationshipScores[$key] ?? 0) * 0.001);
        }

        $reranked = $ids;
        usort($reranked, function (int|string $a, int|string $b) use ($combinedScores, $baseOrder): int {
            $aKey = (string) $a;
            $bKey = (string) $b;
            $scoreCmp = ($combinedScores[$bKey] ?? 0.0) <=> ($combinedScores[$aKey] ?? 0.0);
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }

            return ($baseOrder[$aKey] ?? PHP_INT_MAX) <=> ($baseOrder[$bKey] ?? PHP_INT_MAX);
        });

        if ($reranked === $ids) {
            return ['ids' => $ids, 'applied' => false];
        }

        return ['ids' => $reranked, 'applied' => true];
    }

    /**
     * @return array{ids: array<int|string>, scores: array<string, float>, fallback_reason: ?string}
     */
    private function semanticSearchIds(string $query, string $entityTypeId, int $limit): array
    {
        try {
            $queryVector = $this->embeddingProvider?->embed($query) ?? [];
        } catch (\Throwable $exception) {
            error_log(sprintf(
                '[Waaseyaa] Semantic search provider error; falling back to keyword mode: %s',
                $exception->getMessage(),
            ));
            return ['ids' => [], 'scores' => [], 'fallback_reason' => 'embedding_provider_error'];
        }

        $matches = $this->embeddingStorage->findSimilar($queryVector, $entityTypeId, $limit);

        $ids = [];
        $scores = [];
        foreach ($matches as $match) {
            if (is_array($match) && is_string($match['id'] ?? null) && $match['id'] !== '') {
                $rawId = $match['id'];
                $id = ctype_digit($rawId) ? (int) $rawId : $rawId;
                $ids[] = $id;
                if (is_numeric($match['score'] ?? null)) {
                    $scores[(string) $id] = (float) $match['score'];
                }
            }
        }

        return ['ids' => $ids, 'scores' => $scores, 'fallback_reason' => null];
    }

    /**
     * @param \Waaseyaa\Entity\Storage\EntityStorageInterface $storage
     * @return array<int|string>
     */
    private function keywordSearchIds(EntityStorageInterface $storage, string $query, int $limit): array
    {
        $candidateIds = [];
        foreach (['title', 'name', 'body'] as $field) {
            try {
                $ids = $storage->getQuery()
                    ->condition($field, $query, 'CONTAINS')
                    ->range(0, $limit)
                    ->execute();
            } catch (\Throwable) {
                continue;
            }

            foreach ($ids as $id) {
                if (!in_array($id, $candidateIds, true)) {
                    $candidateIds[] = $id;
                }
                if (count($candidateIds) >= $limit) {
                    break 2;
                }
            }
        }

        return $candidateIds;
    }
}

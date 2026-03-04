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

        if ($this->embeddingProvider !== null) {
            $semanticResult = $this->semanticSearchIds($query, $entityTypeId, $limit);
            if ($semanticResult['fallback_reason'] !== null) {
                $mode = 'keyword';
                $fallbackReason = $semanticResult['fallback_reason'];
                $ids = $this->keywordSearchIds($storage, $query, $limit);
            } else {
                $ids = $semanticResult['ids'];
            }
        } else {
            $ids = $this->keywordSearchIds($storage, $query, $limit);
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

        return JsonApiDocument::fromCollection($resources, meta: $meta);
    }

    /**
     * @return array{ids: array<int|string>, fallback_reason: ?string}
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
            return ['ids' => [], 'fallback_reason' => 'embedding_provider_error'];
        }

        $matches = $this->embeddingStorage->findSimilar($queryVector, $entityTypeId, $limit);

        $ids = [];
        foreach ($matches as $match) {
            if (is_array($match) && is_string($match['id'] ?? null) && $match['id'] !== '') {
                $rawId = $match['id'];
                $ids[] = ctype_digit($rawId) ? (int) $rawId : $rawId;
            }
        }

        return ['ids' => $ids, 'fallback_reason' => null];
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

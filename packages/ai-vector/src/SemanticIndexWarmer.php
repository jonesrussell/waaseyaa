<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Vector;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Event\EntityEvent;

final class SemanticIndexWarmer
{
    public const string CONTRACT_VERSION = 'v1.0';
    public const string CONTRACT_SURFACE = 'semantic_index_warm';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EmbeddingStorageInterface $embeddingStorage,
        private readonly ?EmbeddingProviderInterface $embeddingProvider,
    ) {}

    /**
     * @param list<string> $entityTypeIds
     * @return array{
     *   contract_version: string,
     *   contract_surface: string,
     *   status: string,
     *   requested_entity_types: list<string>,
     *   processed_total: int,
     *   stored_total: int,
     *   removed_total: int,
     *   missing_total: int,
     *   duration_ms: float,
     *   by_type: array<string, array{status: string, candidates: int, processed: int, stored: int, removed: int, missing: int}>
     * }
     */
    public function warm(array $entityTypeIds = ['node'], int $limit = 0): array
    {
        $startedAt = hrtime(true);
        $requestedEntityTypes = $this->normalizeEntityTypeIds($entityTypeIds);

        $report = [
            'contract_version' => self::CONTRACT_VERSION,
            'contract_surface' => self::CONTRACT_SURFACE,
            'status' => 'ok',
            'requested_entity_types' => $requestedEntityTypes,
            'processed_total' => 0,
            'stored_total' => 0,
            'removed_total' => 0,
            'missing_total' => 0,
            'duration_ms' => 0.0,
            'by_type' => [],
        ];

        if ($this->embeddingProvider === null) {
            $report['status'] = 'skipped_no_provider';
            $report['duration_ms'] = $this->durationMs($startedAt);
            return $report;
        }

        $listener = new EntityEmbeddingListener(
            queue: null,
            storage: $this->embeddingStorage,
            embeddingProvider: $this->embeddingProvider,
        );

        foreach ($requestedEntityTypes as $entityTypeId) {
            if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
                $report['by_type'][$entityTypeId] = [
                    'status' => 'missing_entity_type',
                    'candidates' => 0,
                    'processed' => 0,
                    'stored' => 0,
                    'removed' => 0,
                    'missing' => 0,
                ];
                $report['status'] = 'completed_with_skips';
                continue;
            }

            $storage = $this->entityTypeManager->getStorage($entityTypeId);
            $query = $storage->getQuery()->accessCheck(false);
            if ($limit > 0) {
                $query = $query->range(0, $limit);
            }

            $ids = $query->execute();
            usort($ids, static fn(int|string $a, int|string $b): int => strcmp((string) $a, (string) $b));

            $entities = $ids !== [] ? $storage->loadMultiple($ids) : [];

            $typeProcessed = 0;
            $typeStored = 0;
            $typeRemoved = 0;
            $typeMissing = 0;

            foreach ($ids as $id) {
                if (!isset($entities[$id])) {
                    $typeMissing++;
                    continue;
                }

                $entity = $entities[$id];
                if (!$entity instanceof EntityInterface) {
                    $typeMissing++;
                    continue;
                }

                $listener->onPostSave(new EntityEvent($entity));
                $typeProcessed++;

                if ($this->isIndexable($entity)) {
                    $typeStored++;
                } else {
                    $typeRemoved++;
                }
            }

            $report['processed_total'] += $typeProcessed;
            $report['stored_total'] += $typeStored;
            $report['removed_total'] += $typeRemoved;
            $report['missing_total'] += $typeMissing;

            $report['by_type'][$entityTypeId] = [
                'status' => 'ok',
                'candidates' => count($ids),
                'processed' => $typeProcessed,
                'stored' => $typeStored,
                'removed' => $typeRemoved,
                'missing' => $typeMissing,
            ];
        }

        $report['duration_ms'] = $this->durationMs($startedAt);

        return $report;
    }

    private function isIndexable(EntityInterface $entity): bool
    {
        if ($entity->getEntityTypeId() !== 'node') {
            return true;
        }

        $values = $entity->toArray();
        $state = $this->normalizeWorkflowState($values['workflow_state'] ?? null, $values['status'] ?? 0);

        return $state === 'published';
    }

    private function normalizeWorkflowState(mixed $workflowState, mixed $status): string
    {
        if (is_string($workflowState) && trim($workflowState) !== '') {
            return strtolower(trim($workflowState));
        }
        if (is_bool($status)) {
            return $status ? 'published' : 'draft';
        }
        if (is_numeric($status)) {
            return ((int) $status) === 1 ? 'published' : 'draft';
        }
        if (is_string($status)) {
            $normalized = strtolower(trim($status));
            if (in_array($normalized, ['1', 'true', 'published'], true)) {
                return 'published';
            }
        }

        return 'draft';
    }

    /**
     * @param list<string> $entityTypeIds
     * @return list<string>
     */
    private function normalizeEntityTypeIds(array $entityTypeIds): array
    {
        $normalized = [];
        foreach ($entityTypeIds as $entityTypeId) {
            $trimmed = trim($entityTypeId);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return ['node'];
        }

        return $normalized;
    }

    private function durationMs(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }
}

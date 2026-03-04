<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Vector;

interface EmbeddingStorageInterface
{
    /**
     * @param list<float|int> $vector
     */
    public function store(string $entityType, string $id, array $vector): void;

    /**
     * @param list<float|int> $queryVector
     * @return list<array{id: string, score: float}>
     */
    public function findSimilar(array $queryVector, string $entityType, int $limit): array;

    public function delete(string $entityType, string $id): void;
}

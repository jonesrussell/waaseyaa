<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Vector;

/**
 * Interface for embedding providers that generate vector representations of text.
 */
interface EmbeddingInterface extends EmbeddingProviderInterface
{
    /**
     * Generate embeddings for multiple texts.
     *
     * @param string[] $texts
     * @return float[][] Array of embedding vectors.
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the dimensionality of embeddings produced by this provider.
     */
    public function getDimensions(): int;
}

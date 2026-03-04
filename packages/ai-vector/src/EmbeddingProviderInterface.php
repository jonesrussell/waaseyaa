<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Vector;

interface EmbeddingProviderInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;
}

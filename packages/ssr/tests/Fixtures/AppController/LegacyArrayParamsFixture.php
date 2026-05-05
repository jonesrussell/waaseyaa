<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Fixtures\AppController;

/**
 * Legacy controller signature: implicit `array $params` (no binding attribute).
 *
 * Classified by the dispatcher as `MapRoute` via the post-#1390 implicit-array
 * shim and emits a single `implicit_array_shim` deprecation notice with
 * `recommended_attribute=MapRoute`.
 */
final class LegacyArrayParamsFixture
{
    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function show(array $params): array
    {
        return ['ok' => true, 'received' => $params];
    }
}

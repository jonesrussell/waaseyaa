<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Fixtures\AppController;

/**
 * Legacy controller signature: implicit `array $query` (no binding attribute).
 *
 * Classified by the dispatcher as `MapQuery` via the post-#1390 implicit-array
 * shim and emits a single `implicit_array_shim` deprecation notice with
 * `recommended_attribute=MapQuery`.
 */
final class LegacyArrayQueryFixture
{
    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function show(array $query): array
    {
        return ['ok' => true, 'q' => $query];
    }
}

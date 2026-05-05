<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Fixtures\AppController;

use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Modern controller signature: array parameters explicitly annotated with the
 * post-#1390 `MapRoute` / `MapQuery` attributes.
 *
 * The dispatcher must NOT emit any deprecation notice for these parameters.
 */
final class AnnotatedFixture
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function show(#[MapRoute] array $params, #[MapQuery] array $query): array
    {
        return ['ok' => true, 'p' => $params, 'q' => $query];
    }
}

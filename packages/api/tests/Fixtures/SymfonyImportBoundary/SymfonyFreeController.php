<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Fixtures\SymfonyImportBoundary;

use Waaseyaa\Api\Http\JsonApiResponse;
use Waaseyaa\Foundation\Http\Request;

/**
 * Fixture controller for SymfonyImportBoundaryTest.
 *
 * Demonstrates that an app-level controller can produce a JSON:API response
 * using only Waaseyaa-owned framework names — no `use Symfony\…` line in
 * this file. The contract test asserts that property by source-scan and
 * also exercises the controller end-to-end to confirm the produced response
 * carries the canonical JSON:API content type and payload shape.
 *
 * IMPORTANT: do NOT add `use Symfony\…` imports to this file. The contract
 * test (`SymfonyImportBoundaryTest`) will fail if any are introduced.
 */
final class SymfonyFreeController
{
    public function show(Request $request, string $id): JsonApiResponse
    {
        return new JsonApiResponse([
            'data' => [
                'id' => $id,
                'type' => 'symfony_free_resource',
                'attributes' => [
                    'method' => $request->getMethod(),
                ],
            ],
        ], 200);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Fixtures\AppController;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;

/**
 * Mixed controller signature: legacy implicit `array $params` and `array $query`
 * alongside typed framework services (`AccountInterface`, `HttpRequest`).
 *
 * Exactly two `implicit_array_shim` deprecation notices are expected — one per
 * implicit-array parameter — while typed services resolve via the dispatcher's
 * service-resolver path without contributing any notices.
 */
final class MixedFixture
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function show(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $request,
    ): array {
        return [
            'ok' => true,
            'account_id' => $account->id(),
            'method' => $request->getMethod(),
            'p' => $params,
            'q' => $query,
        ];
    }
}

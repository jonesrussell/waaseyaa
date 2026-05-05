<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Fixtures\AppController;

/**
 * Unbound implicit-array signature: `array $somethingElse` whose name matches
 * neither `params` nor `query`.
 *
 * Per the post-#1390 dispatcher contract §3, this is classified as
 * `ImplicitEmptyArray` and bound to `[]` at invocation time. The dispatcher
 * emits a single `implicit_array_unbound` notice with `recommended_attribute=''`.
 */
final class UnboundArrayFixture
{
    /**
     * @param array<string, mixed> $somethingElse
     *
     * @return array<string, mixed>
     */
    public function show(array $somethingElse): array
    {
        return ['ok' => true, 'received' => $somethingElse];
    }
}

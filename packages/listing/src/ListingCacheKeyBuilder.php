<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

/**
 * Builds deterministic cache keys for listing results.
 *
 * FR-037 key format: `listing:<def-hash>:<exposed-hash>:<ctx-hash>` where
 * each hash is a 16-hex-char SHA-256 prefix over canonical JSON.
 *
 * Stable surface (charter §5.X). Single public method `build()` is
 * committed; WP06 may add internal hashing variants without changing
 * the signature.
 *
 * Cross-worker determinism: this class is process-pure (no time, no
 * random, no filesystem access). Two PHP workers with the same inputs
 * MUST produce the same key.
 *
 * @api
 */
final class ListingCacheKeyBuilder
{
    /**
     * Build a deterministic cache key.
     *
     * @param array<string, string> $contextValues Resolved context-name => canonical-value pairs from {@see \Waaseyaa\Cache\ContextResolver::resolve()}.
     *
     * @return non-empty-string
     */
    public function build(
        ListingDefinition $def,
        ExposedFilterValues $exposed,
        array $contextValues,
    ): string {
        return sprintf(
            'listing:%s:%s:%s',
            $def->cacheKeyHash(),
            $exposed->cacheKeyHash(),
            $this->hashContextValues($contextValues),
        );
    }

    /**
     * @param array<string, string> $contextValues
     */
    private function hashContextValues(array $contextValues): string
    {
        ksort($contextValues);
        $json = json_encode(
            $contextValues,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return substr(hash('sha256', $json), 0, 16);
    }
}

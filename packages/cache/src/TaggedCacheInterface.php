<?php

declare(strict_types=1);

namespace Waaseyaa\Cache;

use Waaseyaa\Cache\Exception\InvalidCacheTagException;

/**
 * Tag-aware cache surface used by the listing pipeline.
 *
 * Additive on top of {@see CacheBackendInterface} (this repository's canonical
 * cache contract). Implementations expose an introspectable, key-scoped tag
 * index that supports atomic per-tag invalidation.
 *
 * The interface is intentionally distinct from {@see TagAwareCacheInterface}:
 *
 * - {@see TagAwareCacheInterface::invalidateByTags()} invalidates by a list of
 *   tags, returning void and operating on the existing key→tags shape stored
 *   on {@see CacheItem::$tags}.
 * - {@see self::setWithTags()} / {@see self::invalidateByTag()} operate on a
 *   STRICT tag-string vocabulary with a regex contract and per-call eviction
 *   count, designed for the listing pipeline's deterministic cache-tag flow
 *   (FR-033, FR-034).
 *
 * Tag-string format: `^[a-z][a-z0-9_:.-]*$` — uppercase letters and unlisted
 * special characters are rejected with {@see InvalidCacheTagException}. There
 * is no silent normalisation (no `strtolower`, no character substitution); the
 * caller MUST pass tags that already match the regex. Rationale: codified-
 * context discipline — silent normalisation hides bugs and makes invalidation
 * non-deterministic.
 *
 * Canonical tag vocabulary (emitted by the listing pipeline, consumed by the
 * cache invalidator; documented at mission close in
 * `docs/conventions/cache-tags-and-contexts.md`):
 *
 * - `entity:<type>`              — any entity of `<type>` saved/deleted
 * - `entity:<type>:<id>`         — specific entity saved/deleted
 * - `entity:<type>:<id>:<langcode>` — translatable entity affected per language
 *
 * Stability commitment: the three method signatures below are stable from
 * v0.x. New methods (e.g. `invalidateByTags(array)` mirroring the legacy
 * shape) may be added; the v0.x lower bound is the current surface.
 *
 * @api
 */
interface TaggedCacheInterface extends CacheBackendInterface
{
    /**
     * Strict tag-string regex. Tags MUST match this pattern.
     */
    public const string TAG_REGEX = '/^[a-z][a-z0-9_:.-]*$/';

    /**
     * Store a value with cache tags.
     *
     * @param non-empty-string       $key
     * @param list<non-empty-string> $tags Each MUST match {@see self::TAG_REGEX}
     * @param ?positive-int          $ttl  TTL in seconds; `null` = infinite (eviction via invalidateByTag only)
     *
     * @throws InvalidCacheTagException When any tag fails {@see self::TAG_REGEX}.
     */
    public function setWithTags(string $key, mixed $value, array $tags, ?int $ttl = null): void;

    /**
     * Evict every entry whose tag set includes $tag.
     *
     * @param non-empty-string $tag
     *
     * @return int Best-effort count of evicted entries (zero if the tag is unknown).
     */
    public function invalidateByTag(string $tag): int;

    /**
     * Read-back of the tags associated with a stored key.
     *
     * Returns an empty list if the key is not present (whether evicted, expired,
     * or never stored). Provided primarily for introspection in tests and for
     * the cache invalidator.
     *
     * @param non-empty-string $key
     *
     * @return list<non-empty-string>
     */
    public function getTagsFor(string $key): array;
}

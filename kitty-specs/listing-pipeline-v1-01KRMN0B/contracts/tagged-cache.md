# Contract: TaggedCacheInterface + cache-tag vocabulary

**Stability scope:** Charter §5.Y (cache package additions)
**FRs covered:** FR-033, FR-034
**Owned by:** WP03

## TaggedCacheInterface

```php
namespace Waaseyaa\Cache;

interface TaggedCacheInterface extends CacheInterface
{
    /**
     * Store a value with cache tags.
     *
     * @param non-empty-string $key
     * @param list<non-empty-string> $tags    Must match [a-z][a-z0-9_:.-]*
     * @param ?positive-int $ttl              null = infinite (eviction via invalidateByTag only)
     *
     * @throws InvalidCacheTagException on any tag that doesn't match the regex
     */
    public function setWithTags(string $key, mixed $value, array $tags, ?int $ttl = null): void;

    /**
     * Evict every entry whose tag set includes $tag.
     *
     * @return int  best-effort count of evicted entries (zero if the tag was not present)
     */
    public function invalidateByTag(string $tag): int;

    /**
     * Read-back of the tags associated with a stored key (introspection for tests).
     *
     * @return list<non-empty-string>  empty list if $key not present
     */
    public function getTagsFor(string $key): array;
}
```

**Stability commitment:** Interface and all three method signatures are stable from v0.x. New methods (e.g. `invalidateByTags(array)`) are future additions; current shape is the v0.x lower bound.

**Backwards compatibility:** `Waaseyaa\Cache\CacheInterface` is unchanged. Apps using only key-value caching see no surface drift. Tag-aware operations require an explicit `TaggedCacheInterface` type-hint.

## Tag-string format

**Regex:** `^[a-z][a-z0-9_:.-]*$`

| Character | Allowed |
|---|---|
| `a-z` | yes (always) |
| `0-9` | yes (not as first char) |
| `_`, `:`, `.`, `-` | yes (not as first char) |
| uppercase letters | NO (rejected) |
| any other char | NO (rejected) |

**Enforcement:** `setWithTags()` throws `InvalidCacheTagException` on any tag string that fails the regex. No silent normalisation (no `strtolower`, no character replacement). Rationale: codified-context discipline — silent normalisation hides bugs.

## Canonical tag vocabulary

These are the tag strings the listing pipeline emits + the cache invalidator consumes. Documented in `docs/conventions/cache-tags-and-contexts.md` at mission close (WP12).

| Tag | When emitted | Invalidated by |
|---|---|---|
| `entity:<type>` | `ListingResult::cacheTags()` for any row's entity type | `AfterSaveEvent` / `AfterDeleteEvent` of any entity of that type |
| `entity:<type>:<id>` | Per row in `ListingResult::cacheTags()` | Save/delete of that specific entity |
| `entity:<type>:<id>:<langcode>` | Per row for translatable types, per langcode in `affectedLangcodes` | Save/delete that affected that langcode |

**Future tag namespaces** (not in v0.x, called out so the regex doesn't have to widen):
- `config:<key>` — for cached config-entity reads (M-003 may add)
- `query:<hash>` — for arbitrary EntityQuery caching (would need its own invalidation surface)

## Implementations

| Implementation | Storage | Tag indexing | Notes |
|---|---|---|---|
| `Waaseyaa\Cache\MemoryBackend` (extended in WP03) | in-process array | `array<tag, set<key>>` reverse index | Test default; manifest cache |
| Future Redis backend (post-v1.0) | Redis SETs | `tag:<tag>` keys hold the tagged-key set; `EXPIRE` honoured | Out of scope for this mission |

## InvalidCacheTagException

```php
namespace Waaseyaa\Cache\Exception;

final class InvalidCacheTagException extends \InvalidArgumentException
{
    public function __construct(public readonly string $invalidTag)
    {
        parent::__construct(\sprintf(
            'Cache tag %s does not match [a-z][a-z0-9_:.-]*',
            $invalidTag,
        ));
    }
}
```

## Test surface

`TaggedCacheInterfaceContractTest` (abstract, `#[CoversNothing]`):
- `setWithTagsStoresValue`
- `setWithTagsRejectsInvalidTag` (regex enforcement)
- `setWithTagsAcceptsCanonicalTags` (positive cases for each vocabulary entry)
- `invalidateByTagEvictsTaggedEntries`
- `invalidateByTagReturnsEvictedCount`
- `invalidateByTagUnknownTagReturnsZero`
- `getTagsForReturnsStoredTags`
- `getTagsForUnknownKeyReturnsEmptyList`
- `ttlExpiry` (when `$ttl` is set, entries evict after TTL even without invalidation)

Concrete subclass: `MemoryBackendTaggedTest`.

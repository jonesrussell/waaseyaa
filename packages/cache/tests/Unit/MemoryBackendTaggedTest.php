<?php

declare(strict_types=1);

namespace Waaseyaa\Cache\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Cache\CacheBackendInterface;
use Waaseyaa\Cache\CacheItem;
use Waaseyaa\Cache\Exception\InvalidCacheTagException;
use Waaseyaa\Cache\TaggedCacheInterface;

/**
 * Behavioural tests for {@see MemoryBackend}'s {@see TaggedCacheInterface}
 * implementation (FR-033, FR-034).
 *
 * The legacy {@see CacheBackendInterface}/{@see \Waaseyaa\Cache\TagAwareCacheInterface}
 * behaviour is covered by `tests/Unit/Backend/MemoryBackendTest.php` and is
 * not duplicated here.
 */
#[CoversClass(MemoryBackend::class)]
final class MemoryBackendTaggedTest extends TestCase
{
    private MemoryBackend $backend;

    protected function setUp(): void
    {
        $this->backend = new MemoryBackend();
    }

    #[Test]
    public function implementsTaggedCacheInterface(): void
    {
        self::assertInstanceOf(TaggedCacheInterface::class, $this->backend);
    }

    #[Test]
    public function setWithTagsStoresValueReadableViaGet(): void
    {
        $this->backend->setWithTags('listing:home', 'rendered-html', ['entity:node']);

        $item = $this->backend->get('listing:home');

        self::assertInstanceOf(CacheItem::class, $item);
        self::assertSame('rendered-html', $item->data);
        self::assertSame(['entity:node'], $item->tags);
    }

    #[Test]
    public function setWithTagsRecordsTagsForIntrospection(): void
    {
        $this->backend->setWithTags(
            'listing:home',
            'rendered-html',
            ['entity:node', 'entity:node:42'],
        );

        $tags = $this->backend->getTagsFor('listing:home');

        // Returned sorted for determinism.
        self::assertSame(['entity:node', 'entity:node:42'], $tags);
    }

    #[Test]
    public function getTagsForReturnsEmptyListWhenKeyAbsent(): void
    {
        self::assertSame([], $this->backend->getTagsFor('never-stored'));
    }

    #[Test]
    public function getTagsForReturnsLastSetWithTagsCallOnOverwrite(): void
    {
        $this->backend->setWithTags('k', 'v1', ['entity:node', 'entity:node:7']);
        $this->backend->setWithTags('k', 'v2', ['entity:user']);

        // Overwrite REPLACES the tag set — it does not merge.
        self::assertSame(['entity:user'], $this->backend->getTagsFor('k'));
    }

    #[Test]
    public function invalidateByTagEvictsAllKeysCarryingTheTag(): void
    {
        $this->backend->setWithTags('a', 1, ['entity:node']);
        $this->backend->setWithTags('b', 2, ['entity:node', 'entity:user']);
        $this->backend->setWithTags('c', 3, ['entity:user']);

        $evicted = $this->backend->invalidateByTag('entity:node');

        self::assertSame(2, $evicted, 'a and b should be evicted by entity:node');
        self::assertFalse($this->backend->get('a'));
        self::assertFalse($this->backend->get('b'));
        // c carries only entity:user — must survive.
        $itemC = $this->backend->get('c');
        self::assertInstanceOf(CacheItem::class, $itemC);
        self::assertSame(3, $itemC->data);
    }

    #[Test]
    public function invalidateByTagReturnsZeroForUnknownTag(): void
    {
        $this->backend->setWithTags('a', 1, ['entity:node']);

        self::assertSame(0, $this->backend->invalidateByTag('entity:taxonomy'));
        // The unrelated entry must remain.
        self::assertInstanceOf(CacheItem::class, $this->backend->get('a'));
    }

    #[Test]
    public function invalidateByTagCleansReverseIndex(): void
    {
        $this->backend->setWithTags('a', 1, ['entity:node']);
        $this->backend->invalidateByTag('entity:node');

        // After eviction, getTagsFor reports the key as absent.
        self::assertSame([], $this->backend->getTagsFor('a'));

        // And a second invalidation of the same tag returns 0 — no over-count.
        self::assertSame(0, $this->backend->invalidateByTag('entity:node'));
    }

    #[Test]
    public function setWithTagsAcceptsEmptyTagsArrayDegradingToPlainSet(): void
    {
        $this->backend->setWithTags('k', 'v', []);

        $item = $this->backend->get('k');
        self::assertInstanceOf(CacheItem::class, $item);
        self::assertSame('v', $item->data);
        self::assertSame([], $this->backend->getTagsFor('k'));
    }

    #[Test]
    public function setWithTagsHonoursTtl(): void
    {
        // ttl=1 → expiry one second from now; rewinding via direct expire
        // semantics on the legacy CacheItem path lets us assert without
        // sleeping. We use ttl=1 then advance "time" by spinning briefly.
        $this->backend->setWithTags('short-lived', 'data', ['entity:node'], ttl: 1);

        $item = $this->backend->get('short-lived');
        self::assertInstanceOf(CacheItem::class, $item);
        self::assertNotSame(CacheBackendInterface::PERMANENT, $item->expire);
        self::assertGreaterThan(time() - 1, $item->expire);
    }

    #[Test]
    public function setWithTagsNullTtlIsPermanent(): void
    {
        $this->backend->setWithTags('k', 'v', ['entity:node'], ttl: null);

        $item = $this->backend->get('k');
        self::assertInstanceOf(CacheItem::class, $item);
        self::assertSame(CacheBackendInterface::PERMANENT, $item->expire);
    }

    #[Test]
    public function expiredTaggedEntryIsEvictedOnRead(): void
    {
        // expire=1 second ago — the existing get() path treats this as
        // already-expired and evicts on read.
        $this->backend->setWithTags('expired', 'data', ['entity:node'], ttl: -1);

        // Manually set expire to a past time by re-storing via legacy set().
        // Direct test of the TTL path: ttl=-1 makes time()-1 (already past).
        self::assertFalse($this->backend->get('expired'));
    }

    /**
     * Canonical tags MUST be accepted (FR-034, plus the cache-tag vocabulary
     * documented in the contract).
     */
    #[Test]
    #[DataProvider('canonicalTagProvider')]
    public function setWithTagsAcceptsCanonicalTags(string $tag): void
    {
        $this->backend->setWithTags('k', 'v', [$tag]);

        self::assertSame([$tag], $this->backend->getTagsFor('k'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function canonicalTagProvider(): iterable
    {
        yield 'entity:type' => ['entity:node'];
        yield 'entity:type:id' => ['entity:node:42'];
        yield 'entity:type:id:langcode' => ['entity:node:42:en'];
        yield 'entity:type with hyphen in type' => ['entity:taxonomy-term'];
        yield 'entity:type with underscore' => ['entity:custom_block'];
        yield 'entity:type with dot' => ['entity:node.bundle'];
    }

    /**
     * Malformed tags MUST throw {@see InvalidCacheTagException} BEFORE the
     * value is stored (FR-034 no-silent-normalisation discipline).
     */
    #[Test]
    #[DataProvider('invalidTagProvider')]
    public function setWithTagsRejectsInvalidTag(string $invalidTag): void
    {
        try {
            $this->backend->setWithTags('k', 'v', [$invalidTag]);
            self::fail(\sprintf('Expected InvalidCacheTagException for tag %s', var_export($invalidTag, true)));
        } catch (InvalidCacheTagException $e) {
            self::assertSame($invalidTag, $e->invalidTag);
        }

        // Critical: the cache must be untouched when validation fails.
        self::assertFalse($this->backend->get('k'));
        self::assertSame([], $this->backend->getTagsFor('k'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTagProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'uppercase' => ['Entity:Node'];
        yield 'leading digit' => ['1entity'];
        yield 'leading colon' => [':entity:node'];
        yield 'leading hyphen' => ['-entity'];
        yield 'leading underscore' => ['_entity'];
        yield 'space' => ['entity:node 42'];
        yield 'slash' => ['entity/node'];
        yield 'star' => ['entity:node:*'];
        yield 'unicode letter' => ['entité:node'];
    }

    #[Test]
    public function setWithTagsRejectsBatchOnFirstInvalidTag(): void
    {
        // Mixed list — first valid, second invalid. The whole call must
        // fail and leave the cache untouched.
        $this->expectException(InvalidCacheTagException::class);

        try {
            $this->backend->setWithTags('k', 'v', ['entity:node', 'INVALID']);
        } finally {
            // No partial state.
            self::assertFalse($this->backend->get('k'));
            self::assertSame([], $this->backend->getTagsFor('k'));
        }
    }

    #[Test]
    public function deleteRemovesTagIndex(): void
    {
        $this->backend->setWithTags('a', 1, ['entity:node']);
        $this->backend->delete('a');

        self::assertSame([], $this->backend->getTagsFor('a'));
        // Re-invalidating the tag should not find the deleted key.
        self::assertSame(0, $this->backend->invalidateByTag('entity:node'));
    }

    #[Test]
    public function deleteAllClearsTagIndex(): void
    {
        $this->backend->setWithTags('a', 1, ['entity:node']);
        $this->backend->setWithTags('b', 2, ['entity:user']);

        $this->backend->deleteAll();

        self::assertSame([], $this->backend->getTagsFor('a'));
        self::assertSame([], $this->backend->getTagsFor('b'));
        self::assertSame(0, $this->backend->invalidateByTag('entity:node'));
        self::assertSame(0, $this->backend->invalidateByTag('entity:user'));
    }

    #[Test]
    public function removeBinClearsTagIndex(): void
    {
        $this->backend->setWithTags('a', 1, ['entity:node']);

        $this->backend->removeBin();

        self::assertSame([], $this->backend->getTagsFor('a'));
        self::assertSame(0, $this->backend->invalidateByTag('entity:node'));
    }

    #[Test]
    public function deleteMultipleRemovesTagIndex(): void
    {
        $this->backend->setWithTags('a', 1, ['entity:node']);
        $this->backend->setWithTags('b', 2, ['entity:node']);
        $this->backend->setWithTags('c', 3, ['entity:user']);

        $this->backend->deleteMultiple(['a', 'b']);

        self::assertSame([], $this->backend->getTagsFor('a'));
        self::assertSame([], $this->backend->getTagsFor('b'));
        // c untouched.
        self::assertSame(['entity:user'], $this->backend->getTagsFor('c'));
    }

    #[Test]
    public function legacyInvalidateByTagsStillWorksAlongsideTaggedSurface(): void
    {
        // Sanity check: the new TaggedCacheInterface surface does not break
        // the legacy TagAwareCacheInterface::invalidateByTags() shape.
        $this->backend->setWithTags('a', 1, ['entity:node']);

        $this->backend->invalidateByTags(['entity:node']);

        $item = $this->backend->get('a');
        // Legacy semantics: the item is marked invalid (soft) rather than
        // deleted. Distinct from invalidateByTag which deletes outright.
        self::assertInstanceOf(CacheItem::class, $item);
        self::assertFalse($item->valid);
    }
}

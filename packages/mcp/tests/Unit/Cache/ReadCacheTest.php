<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp\Tests\Unit\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Cache\CacheBackendInterface;
use Waaseyaa\Cache\CacheItem;
use Waaseyaa\Mcp\Cache\ReadCache;

#[CoversClass(ReadCache::class)]
final class ReadCacheTest extends TestCase
{
    #[Test]
    public function buildKeyReturnsNullWithoutBackend(): void
    {
        $cache = new ReadCache(account: $this->anonymousAccount());

        self::assertNull($cache->buildKeyForTool('get_entity', ['type' => 'node']));
    }

    #[Test]
    public function buildKeyReturnsNullForNonCacheableTool(): void
    {
        $backend = $this->createMock(CacheBackendInterface::class);
        $cache = new ReadCache(account: $this->anonymousAccount(), backend: $backend);

        self::assertNull($cache->buildKeyForTool('get_entity', []));
    }

    #[Test]
    public function buildKeyReturnsDeterministicHashForCacheableTool(): void
    {
        $backend = $this->createMock(CacheBackendInterface::class);
        $cache = new ReadCache(account: $this->anonymousAccount(), backend: $backend);

        $key1 = $cache->buildKeyForTool('search_entities', ['query' => 'test']);
        $key2 = $cache->buildKeyForTool('search_entities', ['query' => 'test']);

        self::assertNotNull($key1);
        self::assertSame($key1, $key2);
        self::assertStringStartsWith('mcp_read:v1:', $key1);
    }

    #[Test]
    public function buildKeyDiffersForDifferentArguments(): void
    {
        $backend = $this->createMock(CacheBackendInterface::class);
        $cache = new ReadCache(account: $this->anonymousAccount(), backend: $backend);

        $key1 = $cache->buildKeyForTool('search_entities', ['query' => 'foo']);
        $key2 = $cache->buildKeyForTool('search_entities', ['query' => 'bar']);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function buildKeyDiffersForDifferentAccounts(): void
    {
        $backend = $this->createMock(CacheBackendInterface::class);

        $cache1 = new ReadCache(account: $this->anonymousAccount(), backend: $backend);
        $cache2 = new ReadCache(account: $this->authenticatedAccount(42, ['editor']), backend: $backend);

        $key1 = $cache1->buildKeyForTool('search_entities', ['query' => 'test']);
        $key2 = $cache2->buildKeyForTool('search_entities', ['query' => 'test']);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function isCacheableToolReturnsTrueForReadOnlyTools(): void
    {
        $cache = new ReadCache(account: $this->anonymousAccount());

        self::assertTrue($cache->isCacheableTool('search_entities'));
        self::assertTrue($cache->isCacheableTool('ai_discover'));
        self::assertTrue($cache->isCacheableTool('traverse_relationships'));
        self::assertTrue($cache->isCacheableTool('get_related_entities'));
        self::assertTrue($cache->isCacheableTool('get_knowledge_graph'));
    }

    #[Test]
    public function isCacheableToolReturnsFalseForMutatingTools(): void
    {
        $cache = new ReadCache(account: $this->anonymousAccount());

        self::assertFalse($cache->isCacheableTool('get_entity'));
        self::assertFalse($cache->isCacheableTool('editorial_transition'));
        self::assertFalse($cache->isCacheableTool('list_entity_types'));
    }

    #[Test]
    public function accountContextReturnsStructuredArray(): void
    {
        $cache = new ReadCache(account: $this->authenticatedAccount(42, ['editor', 'admin']));

        $context = $cache->accountContext();

        self::assertTrue($context['authenticated']);
        self::assertSame('42', $context['account_id']);
        self::assertSame(['admin', 'editor'], $context['roles']);
    }

    #[Test]
    public function getReturnsNullWithoutBackend(): void
    {
        $cache = new ReadCache(account: $this->anonymousAccount());

        self::assertNull($cache->get('some_key'));
    }

    #[Test]
    public function getReturnsCachedData(): void
    {
        $backend = $this->createMock(CacheBackendInterface::class);
        $item = new CacheItem(cid: 'k', data: ['cached' => true], created: time(), valid: true);
        $backend->method('get')->willReturn($item);

        $cache = new ReadCache(account: $this->anonymousAccount(), backend: $backend);

        self::assertSame(['cached' => true], $cache->get('k'));
    }

    #[Test]
    public function getReturnsNullForInvalidItem(): void
    {
        $backend = $this->createMock(CacheBackendInterface::class);
        $item = new CacheItem(cid: 'k', data: ['cached' => true], created: time(), valid: false);
        $backend->method('get')->willReturn($item);

        $cache = new ReadCache(account: $this->anonymousAccount(), backend: $backend);

        self::assertNull($cache->get('k'));
    }

    #[Test]
    public function getReturnsNullForCacheMiss(): void
    {
        $backend = $this->createMock(CacheBackendInterface::class);
        $backend->method('get')->willReturn(false);

        $cache = new ReadCache(account: $this->anonymousAccount(), backend: $backend);

        self::assertNull($cache->get('k'));
    }

    #[Test]
    public function setWritesToBackend(): void
    {
        $backend = $this->createMock(CacheBackendInterface::class);
        $backend->expects(self::once())->method('set');

        $cache = new ReadCache(account: $this->anonymousAccount(), backend: $backend);
        $cache->set('k', 'search_entities', ['query' => 'test'], ['results' => []]);
    }

    #[Test]
    public function setDoesNothingWithoutBackend(): void
    {
        $cache = new ReadCache(account: $this->anonymousAccount());
        // Should not throw
        $cache->set('k', 'search_entities', [], []);
        self::assertTrue(true);
    }

    #[Test]
    public function isEnabledReflectsBackendPresence(): void
    {
        $withoutBackend = new ReadCache(account: $this->anonymousAccount());
        self::assertFalse($withoutBackend->isEnabled());

        $backend = $this->createMock(CacheBackendInterface::class);
        $withBackend = new ReadCache(account: $this->anonymousAccount(), backend: $backend);
        self::assertTrue($withBackend->isEnabled());
    }

    private function anonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int|string { return 0; }
            public function hasPermission(string $permission): bool { return false; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };
    }

    /**
     * @param list<string> $roles
     */
    private function authenticatedAccount(int $id, array $roles = ['authenticated']): AccountInterface
    {
        return new class($id, $roles) implements AccountInterface {
            public function __construct(private readonly int $uid, private readonly array $userRoles) {}
            public function id(): int|string { return $this->uid; }
            public function hasPermission(string $permission): bool { return false; }
            public function getRoles(): array { return $this->userRoles; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}

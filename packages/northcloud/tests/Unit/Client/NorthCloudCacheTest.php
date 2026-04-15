<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Tests\Unit\Client;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\NorthCloud\Client\NorthCloudCache;

#[CoversClass(NorthCloudCache::class)]
final class NorthCloudCacheTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    #[Test]
    public function getReturnsNullForMissingKey(): void
    {
        $cache = new NorthCloudCache($this->pdo);

        $this->assertNull($cache->get('https://nc.test/missing'));
    }

    #[Test]
    public function setThenGetRoundtripsValue(): void
    {
        $cache = new NorthCloudCache($this->pdo);
        $cache->set('https://nc.test/hit', '{"ok": true}');

        $this->assertSame('{"ok": true}', $cache->get('https://nc.test/hit'));
    }

    #[Test]
    public function expiredEntriesReturnNull(): void
    {
        $cache = new NorthCloudCache($this->pdo, ttl: -1);
        $cache->set('https://nc.test/hit', 'stale');

        $this->assertNull($cache->get('https://nc.test/hit'));
    }

    #[Test]
    public function clearRemovesAllEntries(): void
    {
        $cache = new NorthCloudCache($this->pdo);
        $cache->set('a', '1');
        $cache->set('b', '2');

        $cache->clear();

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Cache\Tests\Unit;

use Aurora\Cache\CacheBackendInterface;
use Aurora\Cache\CacheItem;
use PHPUnit\Framework\TestCase;

final class CacheItemTest extends TestCase
{
    public function testConstructWithDefaults(): void
    {
        $item = new CacheItem(
            cid: 'test:1',
            data: ['key' => 'value'],
            created: time(),
        );

        $this->assertSame('test:1', $item->cid);
        $this->assertSame(['key' => 'value'], $item->data);
        $this->assertSame(CacheBackendInterface::PERMANENT, $item->expire);
        $this->assertSame([], $item->tags);
        $this->assertTrue($item->valid);
    }

    public function testConstructWithTags(): void
    {
        $item = new CacheItem(
            cid: 'entity:42',
            data: 'cached',
            created: time(),
            tags: ['entity:42', 'entity_list:node'],
        );

        $this->assertSame(['entity:42', 'entity_list:node'], $item->tags);
    }
}

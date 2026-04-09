<?php

declare(strict_types=1);

namespace Waaseyaa\Seo\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Seo\SitemapUrl;

#[CoversClass(SitemapUrl::class)]
final class SitemapUrlTest extends TestCase
{
    #[Test]
    public function it_rejects_empty_loc(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SitemapUrl(loc: '');
    }

    #[Test]
    public function it_rejects_invalid_changefreq(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SitemapUrl(loc: 'https://example.com/a', changefreq: 'sometimes');
    }

    #[Test]
    public function it_rejects_priority_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SitemapUrl(loc: 'https://example.com/a', priority: 1.5);
    }
}

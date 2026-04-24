<?php

declare(strict_types=1);

namespace Waaseyaa\Seo\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Seo\RobotsTxtGenerator;

/**
 * @covers \Waaseyaa\Seo\RobotsTxtGenerator
 */
#[CoversClass(RobotsTxtGenerator::class)]
final class RobotsTxtGeneratorTest extends TestCase
{
    #[Test]
    public function to_text_includes_sitemap_when_set(): void
    {
        $gen = new RobotsTxtGenerator();
        $text = $gen->toText('https://example.com/sitemap.xml', userAgent: 'FooBot', allowPaths: ['/public/'], disallowPaths: ['/admin/']);

        $this->assertStringContainsString('User-agent: FooBot', $text);
        $this->assertStringContainsString('Allow: /public/', $text);
        $this->assertStringContainsString('Disallow: /admin/', $text);
        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $text);
    }
}

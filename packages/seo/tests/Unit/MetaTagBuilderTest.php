<?php

declare(strict_types=1);

namespace Waaseyaa\Seo\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Seo\MetaTagBuilder;

#[CoversClass(MetaTagBuilder::class)]
final class MetaTagBuilderTest extends TestCase
{
    #[Test]
    public function it_escapes_content(): void
    {
        $b = new MetaTagBuilder();
        $html = $b->buildHeadSnippet(
            'A & B',
            'He said "hello"',
            'https://example.com/q?x=1&y=2',
        );

        $this->assertStringContainsString('<title>A &amp; B</title>', $html);
        $this->assertStringContainsString('content="He said &quot;hello&quot;"', $html);
        $this->assertStringContainsString('href="https://example.com/q?x=1&amp;y=2"', $html);
    }
}

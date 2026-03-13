<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Unit\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\SSR\Formatter\HtmlFormatter;

#[CoversClass(HtmlFormatter::class)]
final class HtmlFormatterTest extends TestCase
{
    private HtmlFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new HtmlFormatter();
    }

    #[Test]
    public function stripsScriptTags(): void
    {
        $result = $this->formatter->format('<p>Hello</p><script>alert("xss")</script>');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('<p>Hello</p>', $result);
    }

    #[Test]
    public function stripsEventHandlers(): void
    {
        $result = $this->formatter->format('<img src="x" onerror="alert(1)">');
        $this->assertStringNotContainsString('onerror', $result);
    }

    #[Test]
    public function stripsJavascriptUrls(): void
    {
        $result = $this->formatter->format('<a href="javascript:alert(1)">click</a>');
        $this->assertStringNotContainsString('javascript:', $result);
    }

    #[Test]
    public function allowsSafeHtml(): void
    {
        $html = '<p>Hello <strong>world</strong></p><ul><li>item</li></ul>';
        $result = $this->formatter->format($html);
        $this->assertStringContainsString('<p>Hello <strong>world</strong></p>', $result);
        $this->assertStringContainsString('<ul><li>item</li></ul>', $result);
    }

    #[Test]
    public function handlesNullValue(): void
    {
        $result = $this->formatter->format(null);
        $this->assertSame('', $result);
    }

    #[Test]
    public function handlesEmptyString(): void
    {
        $result = $this->formatter->format('');
        $this->assertSame('', $result);
    }

    #[Test]
    public function stripsIframeTags(): void
    {
        $result = $this->formatter->format('<iframe src="evil.com"></iframe><p>safe</p>');
        $this->assertStringNotContainsString('<iframe', $result);
        $this->assertStringContainsString('<p>safe</p>', $result);
    }

    #[Test]
    public function stripsStyleWithExpression(): void
    {
        $result = $this->formatter->format('<div style="background:url(javascript:alert(1))">text</div>');
        $this->assertStringNotContainsString('javascript:', $result);
    }
}

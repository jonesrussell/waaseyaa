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
    #[Test]
    public function format_returns_empty_string_for_empty_input(): void
    {
        $formatter = new HtmlFormatter();
        $this->assertSame('', $formatter->format(''));
        $this->assertSame('', $formatter->format(null));
    }

    #[Test]
    public function format_preserves_safe_markup(): void
    {
        $formatter = new HtmlFormatter();
        $out = $formatter->format('<p>Hello <strong>world</strong></p>');
        $this->assertStringContainsString('Hello', $out);
        $this->assertStringContainsString('world', $out);
        $this->assertStringNotContainsString('<script', $out);
    }

    #[Test]
    public function format_strips_script_tags(): void
    {
        $formatter = new HtmlFormatter();
        $out = $formatter->format('<p>Hi</p><script>alert(1)</script>');
        $this->assertStringContainsString('Hi', $out);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert', $out);
    }
}

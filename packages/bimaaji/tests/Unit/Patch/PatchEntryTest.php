<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Patch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Patch\PatchEntry;

#[CoversClass(PatchEntry::class)]
final class PatchEntryTest extends TestCase
{
    #[Test]
    public function it_stores_all_properties(): void
    {
        $entry = new PatchEntry(
            filePath: 'src/Entity/Article.php',
            content: '<?php // new content',
            diffText: '--- a/src/Entity/Article.php\n+++ b/src/Entity/Article.php',
            contentHash: hash('sha256', '<?php // new content'),
            unsafe: false,
        );

        self::assertSame('src/Entity/Article.php', $entry->filePath);
        self::assertSame('<?php // new content', $entry->content);
        self::assertSame('--- a/src/Entity/Article.php\n+++ b/src/Entity/Article.php', $entry->diffText);
        self::assertSame(hash('sha256', '<?php // new content'), $entry->contentHash);
        self::assertFalse($entry->unsafe);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $content = '<?php // patched';
        $entry = new PatchEntry(
            filePath: 'src/Entity/Page.php',
            content: $content,
            diffText: '@@ -1 +1 @@',
            contentHash: hash('sha256', $content),
            unsafe: true,
        );

        $array = $entry->toArray();

        self::assertSame('src/Entity/Page.php', $array['file_path']);
        self::assertSame($content, $array['content']);
        self::assertSame('@@ -1 +1 @@', $array['diff_text']);
        self::assertSame(hash('sha256', $content), $array['content_hash']);
        self::assertTrue($array['unsafe']);
    }
}

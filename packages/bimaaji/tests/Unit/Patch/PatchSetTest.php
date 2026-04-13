<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Patch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Patch\PatchEntry;
use Waaseyaa\Bimaaji\Patch\PatchSet;

#[CoversClass(PatchSet::class)]
final class PatchSetTest extends TestCase
{
    #[Test]
    public function it_holds_patches(): void
    {
        $entry = new PatchEntry(
            filePath: 'src/Entity/Article.php',
            content: '<?php // content',
            diffText: '@@ diff @@',
            contentHash: hash('sha256', '<?php // content'),
            unsafe: false,
        );

        $set = new PatchSet([$entry]);

        self::assertCount(1, $set->patches);
        self::assertSame($entry, $set->patches[0]);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $entry = new PatchEntry(
            filePath: 'src/Entity/Article.php',
            content: '<?php // content',
            diffText: '@@ diff @@',
            contentHash: hash('sha256', '<?php // content'),
            unsafe: false,
        );

        $set = new PatchSet([$entry]);
        $array = $set->toArray();

        self::assertArrayHasKey('patches', $array);
        self::assertCount(1, $array['patches']);
        self::assertSame('src/Entity/Article.php', $array['patches'][0]['file_path']);
    }

    #[Test]
    public function has_unsafe_patches_returns_false_when_all_safe(): void
    {
        $entry = new PatchEntry(
            filePath: 'a.php',
            content: '<?php',
            diffText: '',
            contentHash: hash('sha256', '<?php'),
            unsafe: false,
        );

        $set = new PatchSet([$entry]);

        self::assertFalse($set->hasUnsafePatches());
    }

    #[Test]
    public function has_unsafe_patches_returns_true_when_any_unsafe(): void
    {
        $safe = new PatchEntry(
            filePath: 'a.php',
            content: '<?php',
            diffText: '',
            contentHash: hash('sha256', '<?php'),
            unsafe: false,
        );
        $unsafe = new PatchEntry(
            filePath: 'b.php',
            content: '<?php',
            diffText: '',
            contentHash: hash('sha256', '<?php'),
            unsafe: true,
        );

        $set = new PatchSet([$safe, $unsafe]);

        self::assertTrue($set->hasUnsafePatches());
    }

    #[Test]
    public function empty_patch_set_has_no_unsafe_patches(): void
    {
        $set = new PatchSet([]);

        self::assertFalse($set->hasUnsafePatches());
        self::assertCount(0, $set->patches);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\EntityStorage\SaveContext;

/**
 * Value-object behaviour for {@see SaveContext::withTranslations()} (M-004 / WP03, T014).
 *
 * Mirrors the WithLangcodeTest style: immutable builder, fluent composition
 * with the M-006 chain, no regressions on the single-language path.
 *
 * Test contract reference: kitty-specs/.../contracts/save-context-translations.md §6.
 */
#[CoversClass(SaveContext::class)]
final class SaveContextTranslationsTest extends TestCase
{
    #[Test]
    public function defaultContextHasNullTranslations(): void
    {
        $ctx = SaveContext::default();

        self::assertNull($ctx->translations);
    }

    #[Test]
    public function withTranslationsReturnsNewInstanceCarryingList(): void
    {
        $original = SaveContext::default();
        $multi = $original->withTranslations(['en', 'oj', 'fr']);

        self::assertNotSame($original, $multi);
        self::assertNull($original->translations);
        self::assertSame(['en', 'oj', 'fr'], $multi->translations);
    }

    #[Test]
    public function withTranslationsRejectsEmptyList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SaveContext::default()->withTranslations([]);
    }

    #[Test]
    public function withTranslationsRejectsEmptyStringElement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SaveContext::default()->withTranslations(['en', '']);
    }

    #[Test]
    public function withTranslationsRejectsNonStringElement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SaveContext::default()->withTranslations(['en', 42]);
    }

    #[Test]
    public function withTranslationsPreservesWithoutNewRevision(): void
    {
        $ctx = SaveContext::default()
            ->withoutNewRevision()
            ->withTranslations(['en', 'oj']);

        self::assertTrue($ctx->withoutNewRevision);
        self::assertSame(['en', 'oj'], $ctx->translations);
    }

    #[Test]
    public function withTranslationsPreservesAsImport(): void
    {
        $ctx = SaveContext::default()
            ->asImport()
            ->withTranslations(['en', 'oj']);

        self::assertTrue($ctx->isImport);
        self::assertSame(['en', 'oj'], $ctx->translations);
    }

    #[Test]
    public function withTranslationsPreservesLangcode(): void
    {
        // Contract §3 precedence: `langcode` is preserved on the value object;
        // the storage coordinator chooses translations over langcode at save
        // time. This test verifies the value-object survives the layering.
        $ctx = SaveContext::default()
            ->withLangcode('de')
            ->withTranslations(['en', 'fr']);

        self::assertSame('de', $ctx->langcode);
        self::assertSame(['en', 'fr'], $ctx->translations);
    }

    #[Test]
    public function withLangcodePreservesPreviousTranslations(): void
    {
        // Reverse chaining: layering withLangcode after withTranslations must
        // preserve the translations list on the value object.
        $ctx = SaveContext::default()
            ->withTranslations(['en', 'oj'])
            ->withLangcode('de');

        self::assertSame(['en', 'oj'], $ctx->translations);
        self::assertSame('de', $ctx->langcode);
    }

    #[Test]
    public function withTranslationsAcceptsSingleLangcode(): void
    {
        // Contract §5: withTranslations(['en']) is valid and routes through
        // the multi-language transaction wrapper — atomicity guarantee.
        $ctx = SaveContext::default()->withTranslations(['en']);

        self::assertSame(['en'], $ctx->translations);
    }

    #[Test]
    public function withTranslationsAcceptsDuplicates(): void
    {
        // Contract §5: builder accepts duplicates; coordinator dedupes.
        $ctx = SaveContext::default()->withTranslations(['en', 'en', 'fr']);

        self::assertSame(['en', 'en', 'fr'], $ctx->translations);
    }

    #[Test]
    public function withTranslationsReindexesPreservingListShape(): void
    {
        // Builder normalises to list<string> via array_values so phpstan list
        // contracts hold even if callers pass an associative-ish array.
        $ctx = SaveContext::default()->withTranslations([2 => 'en', 9 => 'fr']);

        self::assertSame(['en', 'fr'], $ctx->translations);
    }
}

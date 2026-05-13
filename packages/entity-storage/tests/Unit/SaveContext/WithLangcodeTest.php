<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit\SaveContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\EntityStorage\SaveContext;

/**
 * Value-object behaviour for {@see SaveContext::withLangcode()} (T034 / WP07).
 *
 * SaveContext is an immutable builder: every `with*` method returns a new
 * instance leaving the original untouched. `withLangcode()` extends the
 * pre-existing `withoutNewRevision()` chain without changing call-site shape
 * (see T044/WP05 for the revision flag).
 */
#[CoversClass(SaveContext::class)]
final class WithLangcodeTest extends TestCase
{
    #[Test]
    public function defaultContextHasNullLangcode(): void
    {
        $ctx = SaveContext::default();

        self::assertNull($ctx->langcode);
        self::assertFalse($ctx->withoutNewRevision);
    }

    #[Test]
    public function withLangcodeReturnsNewInstanceCarryingLangcode(): void
    {
        $original = SaveContext::default();
        $tagged = $original->withLangcode('fr');

        self::assertNotSame($original, $tagged);
        self::assertNull($original->langcode);
        self::assertSame('fr', $tagged->langcode);
    }

    #[Test]
    public function withLangcodeRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SaveContext::default()->withLangcode('');
    }

    #[Test]
    public function withLangcodePreservesWithoutNewRevision(): void
    {
        $ctx = SaveContext::default()
            ->withoutNewRevision()
            ->withLangcode('oj');

        self::assertTrue($ctx->withoutNewRevision);
        self::assertSame('oj', $ctx->langcode);
    }

    #[Test]
    public function withoutNewRevisionPreservesLangcode(): void
    {
        // Reverse chaining: pinning a langcode first must survive a later
        // withoutNewRevision call.
        $ctx = SaveContext::default()
            ->withLangcode('oj')
            ->withoutNewRevision();

        self::assertTrue($ctx->withoutNewRevision);
        self::assertSame('oj', $ctx->langcode);
    }

    #[Test]
    public function withLangcodeReplacesPreviousLangcode(): void
    {
        $ctx = SaveContext::default()
            ->withLangcode('fr')
            ->withLangcode('oj');

        self::assertSame('oj', $ctx->langcode);
    }
}

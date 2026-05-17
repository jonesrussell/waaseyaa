<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Exception\EntityTranslationException;

/**
 * Unit tests for the WP04 `historicalRevisionWrite` factory addition to the
 * M-006 unified `EntityTranslationException`
 * (FR-017, FR-040, FR-041, contracts/exception-surface.md §3.2).
 */
#[CoversClass(EntityTranslationException::class)]
final class EntityTranslationExceptionHistoricalRevisionWriteTest extends TestCase
{
    #[Test]
    public function returnsEntityTranslationExceptionInstance(): void
    {
        $ex = EntityTranslationException::historicalRevisionWrite(7, 'oj');

        self::assertInstanceOf(EntityTranslationException::class, $ex);
        self::assertInstanceOf(\DomainException::class, $ex);
    }

    #[Test]
    public function carriesStableHistoricalRevisionWriteCode(): void
    {
        $ex = EntityTranslationException::historicalRevisionWrite(7, 'oj');

        self::assertSame('historical_revision_write', $ex->getCode());
    }

    #[Test]
    public function messageContainsVidAndLangcode(): void
    {
        $ex = EntityTranslationException::historicalRevisionWrite(7, 'oj');

        self::assertStringContainsString('7', $ex->getMessage());
        self::assertStringContainsString('oj', $ex->getMessage());
        self::assertStringContainsString('historical revision', $ex->getMessage());
    }

    #[Test]
    public function vidAndLangcodeAreInterpolatedDistinctly(): void
    {
        $ex = EntityTranslationException::historicalRevisionWrite(42, 'fr');

        self::assertStringContainsString('vid=42', $ex->getMessage());
        self::assertStringContainsString('langcode=fr', $ex->getMessage());
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite\Translator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\Translator\SqliteIdentifier;

#[CoversClass(SqliteIdentifier::class)]
final class SqliteIdentifierTest extends TestCase
{
    #[Test]
    public function quotesIdentifierWithDoubleQuotes(): void
    {
        self::assertSame('"users"', SqliteIdentifier::quote('users'));
    }

    #[Test]
    public function escapesEmbeddedDoubleQuoteByDoubling(): void
    {
        self::assertSame('"a""b"', SqliteIdentifier::quote('a"b'));
    }

    #[Test]
    public function literalNullRendersAsKeyword(): void
    {
        self::assertSame('NULL', SqliteIdentifier::literal(null));
    }

    #[Test]
    public function literalBooleanRendersAsZeroOrOne(): void
    {
        self::assertSame('1', SqliteIdentifier::literal(true));
        self::assertSame('0', SqliteIdentifier::literal(false));
    }

    #[Test]
    public function literalIntegerRendersBare(): void
    {
        self::assertSame('42', SqliteIdentifier::literal(42));
        self::assertSame('-7', SqliteIdentifier::literal(-7));
    }

    #[Test]
    public function literalFloatRendersWithoutPreserveZeroFraction(): void
    {
        self::assertSame('3.14', SqliteIdentifier::literal(3.14));
    }

    #[Test]
    public function literalStringSinglequotedAndEscaped(): void
    {
        self::assertSame("'plain'", SqliteIdentifier::literal('plain'));
        self::assertSame("'it''s fine'", SqliteIdentifier::literal("it's fine"));
    }

    #[Test]
    public function literalArrayIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqliteIdentifier::literal(['a', 'b']);
    }

    #[Test]
    public function literalNanIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqliteIdentifier::literal(NAN);
    }

    #[Test]
    public function literalInfIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqliteIdentifier::literal(INF);
    }
}

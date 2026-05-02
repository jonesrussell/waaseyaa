<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\CanonicalJson;

#[CoversClass(CanonicalJson::class)]
final class CanonicalJsonTest extends TestCase
{
    #[Test]
    public function sortsObjectKeysLexicographically(): void
    {
        $value = ['z' => 1, 'a' => 2, 'm' => 3];

        self::assertSame('{"a":2,"m":3,"z":1}', CanonicalJson::encode($value));
    }

    #[Test]
    public function sortsNestedObjectKeys(): void
    {
        $value = ['outer' => ['z' => 1, 'a' => 2]];

        self::assertSame('{"outer":{"a":2,"z":1}}', CanonicalJson::encode($value));
    }

    #[Test]
    public function preservesListOrder(): void
    {
        self::assertSame('["b","a","c"]', CanonicalJson::encode(['b', 'a', 'c']));
    }

    #[Test]
    public function preservesListOrderInsideObjects(): void
    {
        $value = ['cols' => ['b', 'a', 'c']];

        self::assertSame('{"cols":["b","a","c"]}', CanonicalJson::encode($value));
    }

    #[Test]
    public function emitsNoWhitespace(): void
    {
        $json = CanonicalJson::encode(['a' => 1, 'b' => [2, 3]]);

        self::assertStringNotContainsString(' ', $json);
        self::assertStringNotContainsString("\n", $json);
        self::assertStringNotContainsString("\t", $json);
    }

    #[Test]
    public function emitsIntegersWithoutDecimal(): void
    {
        self::assertSame('{"n":42}', CanonicalJson::encode(['n' => 42]));
    }

    #[Test]
    public function preservesNullValues(): void
    {
        self::assertSame('{"a":null,"b":null}', CanonicalJson::encode(['b' => null, 'a' => null]));
    }

    #[Test]
    public function roundTripsUtf8Emoji(): void
    {
        $value = ['name' => 'tag_😀_field', 'table' => 'posts_中文'];

        $json = CanonicalJson::encode($value);

        self::assertSame('{"name":"tag_😀_field","table":"posts_中文"}', $json);
        self::assertStringNotContainsString('\u', $json, 'Multibyte chars must not be \uXXXX-escaped');
    }

    #[Test]
    public function preservesForwardSlashesUnescaped(): void
    {
        self::assertSame('{"path":"a/b/c"}', CanonicalJson::encode(['path' => 'a/b/c']));
    }

    #[Test]
    public function throwsOnNan(): void
    {
        $this->expectException(\JsonException::class);

        CanonicalJson::encode(['n' => NAN]);
    }

    #[Test]
    public function throwsOnInf(): void
    {
        $this->expectException(\JsonException::class);

        CanonicalJson::encode(['n' => INF]);
    }

    #[Test]
    public function throwsOnMalformedUtf8(): void
    {
        $this->expectException(\JsonException::class);

        CanonicalJson::encode(['name' => "\xC3\x28"]);
    }

    #[Test]
    public function canonicalizeIsIdempotent(): void
    {
        $value = ['z' => ['m' => 1, 'a' => 2], 'a' => [3, 1, 2]];

        $once = CanonicalJson::canonicalize($value);
        $twice = CanonicalJson::canonicalize($once);

        self::assertSame($once, $twice);
    }

    #[Test]
    public function scalarValuesPassThroughCanonicalize(): void
    {
        self::assertSame(42, CanonicalJson::canonicalize(42));
        self::assertSame('x', CanonicalJson::canonicalize('x'));
        self::assertNull(CanonicalJson::canonicalize(null));
        self::assertTrue(CanonicalJson::canonicalize(true));
    }
}

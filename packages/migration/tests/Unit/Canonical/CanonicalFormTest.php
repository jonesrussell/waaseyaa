<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Canonical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Canonical\CanonicalForm;

#[CoversClass(CanonicalForm::class)]
final class CanonicalFormTest extends TestCase
{
    #[Test]
    public function associative_keys_are_sorted(): void
    {
        $a = CanonicalForm::encode(['b' => 2, 'a' => 1, 'c' => 3]);
        $b = CanonicalForm::encode(['c' => 3, 'a' => 1, 'b' => 2]);

        self::assertSame($a, $b);
        self::assertSame('{"a":1,"b":2,"c":3}', $a);
    }

    #[Test]
    public function nested_associative_keys_are_recursively_sorted(): void
    {
        $a = CanonicalForm::encode([
            'outer' => [
                'z' => 'last',
                'a' => 'first',
            ],
        ]);
        $b = CanonicalForm::encode([
            'outer' => [
                'a' => 'first',
                'z' => 'last',
            ],
        ]);

        self::assertSame($a, $b);
        self::assertStringContainsString('"a":"first","z":"last"', $a);
    }

    #[Test]
    public function lists_preserve_insertion_order(): void
    {
        $a = CanonicalForm::encode(['items' => [3, 1, 2]]);

        self::assertSame('{"items":[3,1,2]}', $a);
    }

    #[Test]
    public function scalars_encode_without_implicit_string_cast(): void
    {
        $a = CanonicalForm::encode(['id' => 42]);
        $b = CanonicalForm::encode(['id' => '42']);

        self::assertNotSame($a, $b);
        self::assertSame('{"id":42}', $a);
        self::assertSame('{"id":"42"}', $b);
    }

    #[Test]
    public function booleans_and_null_encode_natively(): void
    {
        $out = CanonicalForm::encode([
            'flag' => true,
            'other' => false,
            'maybe' => null,
        ]);

        self::assertSame('{"flag":true,"maybe":null,"other":false}', $out);
    }

    #[Test]
    public function unicode_is_preserved_verbatim(): void
    {
        $word = 'Anishinaabemowin';
        $out = CanonicalForm::encode(['title' => $word]);

        self::assertStringContainsString($word, $out);
        // Confirm JSON_UNESCAPED_UNICODE — the raw bytes appear, not \uXXXX escapes.
        self::assertStringNotContainsString('\\u', $out);
    }

    #[Test]
    public function forward_slashes_are_not_escaped(): void
    {
        $out = CanonicalForm::encode(['path' => 'a/b/c']);

        self::assertSame('{"path":"a/b/c"}', $out);
    }

    #[Test]
    public function encoding_is_deterministic_across_invocations(): void
    {
        $input = [
            'sourceType' => 'wordpress_post',
            'keys' => ['post_id' => 42, 'site_id' => 1],
        ];

        $first = CanonicalForm::encode($input);
        for ($i = 0; $i < 100; $i++) {
            self::assertSame($first, CanonicalForm::encode($input));
        }
    }

    #[Test]
    public function rejects_object_leaves(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // @phpstan-ignore-next-line — intentionally passing an object to trigger the guard.
        CanonicalForm::encode(['bad' => new \stdClass()]);
    }
}

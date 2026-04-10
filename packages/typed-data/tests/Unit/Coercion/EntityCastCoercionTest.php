<?php

declare(strict_types=1);

namespace Waaseyaa\TypedData\Tests\Unit\Coercion;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\TypedData\Coercion\CoercionException;
use Waaseyaa\TypedData\Coercion\EntityCastCoercion;
use Waaseyaa\TypedData\Tests\Unit\Coercion\Fixture\SampleBackedStringEnum;

/**
 * @covers \Waaseyaa\TypedData\Coercion\EntityCastCoercion
 */
final class EntityCastCoercionTest extends TestCase
{
    private const F = 'f';

    #[Test]
    #[DataProvider('intRoundTripProvider')]
    public function int_round_trip(mixed $stored, int $expectedDomain): void
    {
        self::assertSame($expectedDomain, EntityCastCoercion::castInInt(self::F, $stored));
        self::assertSame($expectedDomain, EntityCastCoercion::castOutInt(self::F, $expectedDomain));
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function intRoundTripProvider(): iterable
    {
        yield 'int' => [7, 7];
        yield 'numeric string' => ['7', 7];
        yield 'float truncates' => [7.9, 7];
        yield 'true' => [true, 1];
        yield 'false' => [false, 0];
    }

    #[Test]
    public function int_cast_in_rejects_empty_string(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castInInt(self::F, '');
    }

    #[Test]
    public function int_cast_in_rejects_non_numeric_string(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castInInt(self::F, 'nope');
    }

    #[Test]
    #[DataProvider('floatRoundTripProvider')]
    public function float_round_trip(mixed $stored, float $expectedDomain): void
    {
        self::assertSame($expectedDomain, EntityCastCoercion::castInFloat(self::F, $stored));
        self::assertSame($expectedDomain, EntityCastCoercion::castOutFloat(self::F, $expectedDomain));
    }

    /**
     * @return iterable<string, array{mixed, float}>
     */
    public static function floatRoundTripProvider(): iterable
    {
        yield 'float' => [1.5, 1.5];
        yield 'int widens' => [2, 2.0];
        yield 'numeric string' => ['2.5', 2.5];
        yield 'true' => [true, 1.0];
        yield 'false' => [false, 0.0];
    }

    #[Test]
    public function float_cast_in_rejects_empty_string(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castInFloat(self::F, '');
    }

    #[Test]
    #[DataProvider('boolRoundTripProvider')]
    public function bool_round_trip(mixed $stored, bool $expected): void
    {
        self::assertSame($expected, EntityCastCoercion::castInBool(self::F, $stored));
        self::assertSame($expected, EntityCastCoercion::castOutBool(self::F, $expected));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function boolRoundTripProvider(): iterable
    {
        yield 'bool true' => [true, true];
        yield 'bool false' => [false, false];
        yield 'int 1' => [1, true];
        yield 'int 0' => [0, false];
        yield 'string 1' => ['1', true];
        yield 'string 0' => ['0', false];
        yield 'string true' => ['true', true];
        yield 'string false' => ['false', false];
        yield 'empty string is false' => ['', false];
    }

    #[Test]
    public function bool_cast_in_rejects_float(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castInBool(self::F, 0.5);
    }

    #[Test]
    public function bool_cast_out_rejects_float(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castOutBool(self::F, 0.5);
    }

    #[Test]
    public function string_round_trip(): void
    {
        self::assertSame('hello', EntityCastCoercion::castInString(self::F, 'hello'));
        self::assertSame('7', EntityCastCoercion::castInString(self::F, 7));
        self::assertSame('hello', EntityCastCoercion::castOutString(self::F, 'hello'));
        self::assertSame('7', EntityCastCoercion::castOutString(self::F, 7));
    }

    #[Test]
    public function string_cast_out_backed_enum(): void
    {
        self::assertSame(
            'a',
            EntityCastCoercion::castOutString(self::F, SampleBackedStringEnum::A),
        );
    }

    #[Test]
    public function string_cast_in_rejects_array(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castInString(self::F, []);
    }

    #[Test]
    public function array_round_trip_json_string(): void
    {
        $json = '{"a":1,"b":[2,3]}';
        $domain = EntityCastCoercion::castInArray(self::F, $json);
        self::assertSame(['a' => 1, 'b' => [2, 3]], $domain);
        self::assertSame($json, EntityCastCoercion::castOutArray(self::F, $domain));
    }

    #[Test]
    public function array_cast_in_passes_through_array(): void
    {
        $arr = ['x' => 1];
        self::assertSame($arr, EntityCastCoercion::castInArray(self::F, $arr));
    }

    #[Test]
    public function array_cast_in_empty_string_throws(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castInArray(self::F, '');
    }

    #[Test]
    public function array_cast_in_invalid_json_throws(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castInArray(self::F, '{');
    }

    #[Test]
    public function array_cast_in_json_scalar_throws(): void
    {
        $this->expectException(CoercionException::class);
        EntityCastCoercion::castInArray(self::F, '42');
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\FilterDefinition;
use Waaseyaa\Listing\Operator;

#[CoversClass(FilterDefinition::class)]
final class FilterDefinitionTest extends TestCase
{
    #[Test]
    public function assignsConstructorProperties(): void
    {
        $f = new FilterDefinition('title', Operator::EQ, 'hello');

        self::assertSame('title', $f->field);
        self::assertSame(Operator::EQ, $f->op);
        self::assertSame('hello', $f->value);
        self::assertNull($f->exposedParam);
    }

    #[Test]
    public function emptyFieldRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('', Operator::EQ, 'x');
    }

    #[Test]
    public function exposedParamMatchesPatternWhenSet(): void
    {
        $f = new FilterDefinition('title', Operator::EQ, 'x', 'q1');
        self::assertSame('q1', $f->exposedParam);
    }

    #[Test]
    public function exposedParamCannotStartWithDigit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('title', Operator::EQ, 'x', '1bad');
    }

    #[Test]
    public function exposedParamCannotHaveDashOrUpperCase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('title', Operator::EQ, 'x', 'Bad-Name');
    }

    #[Test]
    public function withExposedReturnsCloneWithParamSet(): void
    {
        $base = new FilterDefinition('title', Operator::EQ, 'x');
        $bound = $base->withExposed('q');

        self::assertNotSame($base, $bound);
        self::assertNull($base->exposedParam);
        self::assertSame('q', $bound->exposedParam);
        self::assertSame('title', $bound->field);
        self::assertSame(Operator::EQ, $bound->op);
        self::assertSame('x', $bound->value);
    }

    // ----- Operator-to-value matrix (positive) -----

    #[Test]
    public function eqAcceptsScalar(): void
    {
        self::assertSame('x', (new FilterDefinition('f', Operator::EQ, 'x'))->value);
        self::assertSame(1, (new FilterDefinition('f', Operator::EQ, 1))->value);
        self::assertTrue((new FilterDefinition('f', Operator::EQ, true))->value);
    }

    #[Test]
    public function eqAcceptsNull(): void
    {
        self::assertNull((new FilterDefinition('f', Operator::EQ, null))->value);
    }

    #[Test]
    public function neqAcceptsScalarOrNull(): void
    {
        self::assertSame('x', (new FilterDefinition('f', Operator::NEQ, 'x'))->value);
        self::assertNull((new FilterDefinition('f', Operator::NEQ, null))->value);
    }

    #[Test]
    #[DataProvider('comparableScalarProvider')]
    public function comparisonOperatorsAcceptNonNullScalar(Operator $op, mixed $value): void
    {
        $f = new FilterDefinition('f', $op, $value);
        self::assertSame($value, $f->value);
    }

    /**
     * @return iterable<string, array{0: Operator, 1: int|string|float}>
     */
    public static function comparableScalarProvider(): iterable
    {
        foreach ([Operator::LT, Operator::LTE, Operator::GT, Operator::GTE] as $op) {
            yield $op->value . '/int' => [$op, 5];
            yield $op->value . '/string' => [$op, 'z'];
            yield $op->value . '/float' => [$op, 1.5];
        }
    }

    #[Test]
    public function inAcceptsNonEmptyList(): void
    {
        $f = new FilterDefinition('f', Operator::IN, [1, 2, 3]);
        self::assertSame([1, 2, 3], $f->value);
    }

    #[Test]
    public function notInAcceptsNonEmptyList(): void
    {
        $f = new FilterDefinition('f', Operator::NOT_IN, ['a']);
        self::assertSame(['a'], $f->value);
    }

    #[Test]
    public function isNullAcceptsNull(): void
    {
        self::assertNull((new FilterDefinition('f', Operator::IS_NULL, null))->value);
    }

    #[Test]
    public function isNotNullAcceptsNull(): void
    {
        self::assertNull((new FilterDefinition('f', Operator::IS_NOT_NULL, null))->value);
    }

    #[Test]
    public function betweenAcceptsTwoElementTuple(): void
    {
        $f = new FilterDefinition('f', Operator::BETWEEN, [1, 10]);
        self::assertSame([1, 10], $f->value);
    }

    #[Test]
    public function startsWithAcceptsString(): void
    {
        self::assertSame('foo', (new FilterDefinition('f', Operator::STARTS_WITH, 'foo'))->value);
    }

    #[Test]
    public function containsAcceptsString(): void
    {
        self::assertSame('foo', (new FilterDefinition('f', Operator::CONTAINS, 'foo'))->value);
    }

    // ----- Operator-to-value matrix (negative) -----

    #[Test]
    public function eqRejectsArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::EQ, ['a']);
    }

    #[Test]
    public function ltRejectsNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::LT, null);
    }

    #[Test]
    public function gtRejectsArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::GT, [1]);
    }

    #[Test]
    public function inRejectsEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::IN, []);
    }

    #[Test]
    public function notInRejectsEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::NOT_IN, []);
    }

    #[Test]
    public function inRejectsAssociativeArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::IN, ['a' => 1]);
    }

    #[Test]
    public function inRejectsScalar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::IN, 'x');
    }

    #[Test]
    public function isNullRejectsNonNullValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::IS_NULL, 'x');
    }

    #[Test]
    public function isNotNullRejectsNonNullValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::IS_NOT_NULL, 0);
    }

    #[Test]
    public function betweenRejectsSingleElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::BETWEEN, [1]);
    }

    #[Test]
    public function betweenRejectsThreeElements(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::BETWEEN, [1, 2, 3]);
    }

    #[Test]
    public function betweenRejectsAssociative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::BETWEEN, ['low' => 1, 'high' => 2]);
    }

    #[Test]
    public function startsWithRejectsNonString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::STARTS_WITH, 42);
    }

    #[Test]
    public function containsRejectsNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FilterDefinition('f', Operator::CONTAINS, null);
    }
}

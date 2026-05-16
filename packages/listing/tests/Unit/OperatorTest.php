<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\Operator;

#[CoversClass(Operator::class)]
final class OperatorTest extends TestCase
{
    #[Test]
    public function backingValuesAreStableLowerSnakeCase(): void
    {
        self::assertSame('eq', Operator::EQ->value);
        self::assertSame('neq', Operator::NEQ->value);
        self::assertSame('lt', Operator::LT->value);
        self::assertSame('lte', Operator::LTE->value);
        self::assertSame('gt', Operator::GT->value);
        self::assertSame('gte', Operator::GTE->value);
        self::assertSame('in', Operator::IN->value);
        self::assertSame('not_in', Operator::NOT_IN->value);
        self::assertSame('is_null', Operator::IS_NULL->value);
        self::assertSame('is_not_null', Operator::IS_NOT_NULL->value);
        self::assertSame('between', Operator::BETWEEN->value);
        self::assertSame('starts_with', Operator::STARTS_WITH->value);
        self::assertSame('contains', Operator::CONTAINS->value);
    }

    #[Test]
    public function thirteenCasesAreDefined(): void
    {
        self::assertCount(13, Operator::cases());
    }

    #[Test]
    public function backingValuesAreUnique(): void
    {
        $values = array_map(static fn (Operator $o): string => $o->value, Operator::cases());
        self::assertSame(array_unique($values), $values);
    }

    #[Test]
    public function tryFromKnownStringReturnsCase(): void
    {
        self::assertSame(Operator::BETWEEN, Operator::tryFrom('between'));
    }

    #[Test]
    public function tryFromUnknownStringReturnsNull(): void
    {
        self::assertNull(Operator::tryFrom('nope'));
    }
}

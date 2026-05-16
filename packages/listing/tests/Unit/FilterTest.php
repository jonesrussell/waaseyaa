<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\Filter;
use Waaseyaa\Listing\FilterDefinition;
use Waaseyaa\Listing\Operator;

#[CoversClass(Filter::class)]
final class FilterTest extends TestCase
{
    #[Test]
    public function eqFactory(): void
    {
        $f = Filter::eq('title', 'hi');
        self::assertSame('title', $f->field);
        self::assertSame(Operator::EQ, $f->op);
        self::assertSame('hi', $f->value);
    }

    #[Test]
    public function neqFactory(): void
    {
        self::assertSame(Operator::NEQ, Filter::neq('f', 1)->op);
    }

    #[Test]
    public function comparisonFactories(): void
    {
        self::assertSame(Operator::LT, Filter::lt('f', 1)->op);
        self::assertSame(Operator::LTE, Filter::lte('f', 1)->op);
        self::assertSame(Operator::GT, Filter::gt('f', 1)->op);
        self::assertSame(Operator::GTE, Filter::gte('f', 1)->op);
    }

    #[Test]
    public function inAndNotInFactories(): void
    {
        $in = Filter::in('f', [1, 2]);
        self::assertSame(Operator::IN, $in->op);
        self::assertSame([1, 2], $in->value);

        $notIn = Filter::notIn('f', ['a']);
        self::assertSame(Operator::NOT_IN, $notIn->op);
        self::assertSame(['a'], $notIn->value);
    }

    #[Test]
    public function isNullAndIsNotNullFactories(): void
    {
        $a = Filter::isNull('f');
        self::assertSame(Operator::IS_NULL, $a->op);
        self::assertNull($a->value);

        $b = Filter::isNotNull('f');
        self::assertSame(Operator::IS_NOT_NULL, $b->op);
        self::assertNull($b->value);
    }

    #[Test]
    public function betweenFactory(): void
    {
        $f = Filter::between('starts_at', '2025-01-01', '2025-12-31');
        self::assertSame(Operator::BETWEEN, $f->op);
        self::assertSame(['2025-01-01', '2025-12-31'], $f->value);
    }

    #[Test]
    public function startsWithFactory(): void
    {
        $f = Filter::startsWith('slug', 'foo');
        self::assertSame(Operator::STARTS_WITH, $f->op);
        self::assertSame('foo', $f->value);
    }

    #[Test]
    public function containsFactory(): void
    {
        $f = Filter::contains('body', 'lorem');
        self::assertSame(Operator::CONTAINS, $f->op);
        self::assertSame('lorem', $f->value);
    }

    #[Test]
    public function langcodeFactoryEmitsCanonicalFieldName(): void
    {
        $f = Filter::langcode('oj');
        self::assertSame('langcode', $f->field);
        self::assertSame(Operator::EQ, $f->op);
        self::assertSame('oj', $f->value);
    }

    #[Test]
    public function exposedFactoryReturnsBoundDefinition(): void
    {
        $base = Filter::eq('title', 'x');
        $bound = Filter::exposed($base, 'q');

        self::assertNotSame($base, $bound);
        self::assertNull($base->exposedParam);
        self::assertSame('q', $bound->exposedParam);
    }

    #[Test]
    public function factoryConstructorIsPrivate(): void
    {
        $rc = new \ReflectionClass(Filter::class);
        self::assertTrue($rc->getConstructor()?->isPrivate(), 'Filter constructor must be private (factory-only).');
    }

    #[Test]
    public function factoriesReturnFilterDefinitionInstances(): void
    {
        self::assertInstanceOf(FilterDefinition::class, Filter::eq('f', 'v'));
        self::assertInstanceOf(FilterDefinition::class, Filter::isNull('f'));
        self::assertInstanceOf(FilterDefinition::class, Filter::between('f', 1, 2));
        self::assertInstanceOf(FilterDefinition::class, Filter::langcode('en'));
    }
}

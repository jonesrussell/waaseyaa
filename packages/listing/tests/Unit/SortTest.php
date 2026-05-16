<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\Sort;
use Waaseyaa\Listing\SortDefinition;
use Waaseyaa\Listing\SortDirection;

#[CoversClass(Sort::class)]
final class SortTest extends TestCase
{
    #[Test]
    public function ascFactoryReturnsAscendingSort(): void
    {
        $s = Sort::asc('created');
        self::assertInstanceOf(SortDefinition::class, $s);
        self::assertSame('created', $s->field);
        self::assertSame(SortDirection::ASC, $s->direction);
    }

    #[Test]
    public function descFactoryReturnsDescendingSort(): void
    {
        $s = Sort::desc('weight');
        self::assertSame('weight', $s->field);
        self::assertSame(SortDirection::DESC, $s->direction);
    }

    #[Test]
    public function factoryConstructorIsPrivate(): void
    {
        $rc = new \ReflectionClass(Sort::class);
        self::assertTrue($rc->getConstructor()?->isPrivate(), 'Sort constructor must be private (factory-only).');
    }
}

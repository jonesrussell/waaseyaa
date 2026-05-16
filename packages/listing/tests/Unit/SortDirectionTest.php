<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\SortDirection;

#[CoversClass(SortDirection::class)]
final class SortDirectionTest extends TestCase
{
    #[Test]
    public function backingValuesAreAscDesc(): void
    {
        self::assertSame('asc', SortDirection::ASC->value);
        self::assertSame('desc', SortDirection::DESC->value);
    }

    #[Test]
    public function exactlyTwoCases(): void
    {
        self::assertCount(2, SortDirection::cases());
    }
}

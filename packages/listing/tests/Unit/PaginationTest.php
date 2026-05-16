<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\Pagination;

#[CoversClass(Pagination::class)]
final class PaginationTest extends TestCase
{
    #[Test]
    public function exposesAllSixPropertiesFromConstructor(): void
    {
        $p = new Pagination(2, 10, 25, 3, true, true);

        self::assertSame(2, $p->page);
        self::assertSame(10, $p->pageSize);
        self::assertSame(25, $p->totalRows);
        self::assertSame(3, $p->totalPages);
        self::assertTrue($p->hasPrev);
        self::assertTrue($p->hasNext);
    }

    #[Test]
    public function approximateTotalNullsTotalRowsAndTotalPages(): void
    {
        $p = new Pagination(1, 10, null, null, false, true);
        self::assertNull($p->totalRows);
        self::assertNull($p->totalPages);
    }

    #[Test]
    public function rejectsNonPositivePage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Pagination(0, 10, 0, 1, false, false);
    }

    #[Test]
    public function rejectsNonPositivePageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Pagination(1, 0, 0, 1, false, false);
    }

    #[Test]
    public function rejectsNegativeTotalRows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Pagination(1, 10, -1, 1, false, false);
    }

    #[Test]
    public function rejectsZeroTotalPagesWhenSet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Pagination(1, 10, 0, 0, false, false);
    }

    #[Test]
    public function rejectsMismatchedNullCombination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Pagination(1, 10, 5, null, false, false);
    }
}

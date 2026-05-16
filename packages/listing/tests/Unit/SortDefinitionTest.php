<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\SortDefinition;
use Waaseyaa\Listing\SortDirection;

#[CoversClass(SortDefinition::class)]
final class SortDefinitionTest extends TestCase
{
    #[Test]
    public function defaultsToAscending(): void
    {
        $s = new SortDefinition('created');
        self::assertSame('created', $s->field);
        self::assertSame(SortDirection::ASC, $s->direction);
    }

    #[Test]
    public function explicitDescending(): void
    {
        $s = new SortDefinition('created', SortDirection::DESC);
        self::assertSame(SortDirection::DESC, $s->direction);
    }

    #[Test]
    public function emptyFieldRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SortDefinition('');
    }
}

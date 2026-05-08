<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Io;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Io\StringQueueStdinSource;

#[CoversClass(StringQueueStdinSource::class)]
final class StringQueueStdinSourceTest extends TestCase
{
    #[Test]
    public function readLineReturnsLinesInOrder(): void
    {
        $source = new StringQueueStdinSource(['first', 'second', 'third']);

        self::assertSame('first', $source->readLine());
        self::assertSame('second', $source->readLine());
        self::assertSame('third', $source->readLine());
    }

    #[Test]
    public function readLineReturnsNullWhenExhausted(): void
    {
        $source = new StringQueueStdinSource(['only']);
        $source->readLine();

        self::assertNull($source->readLine());
        // Further calls also return null.
        self::assertNull($source->readLine());
    }

    #[Test]
    public function readLineReturnsNullOnEmptyQueue(): void
    {
        $source = new StringQueueStdinSource([]);

        self::assertNull($source->readLine());
    }

    #[Test]
    public function isInteractiveReturnsFalse(): void
    {
        $source = new StringQueueStdinSource(['yes']);

        self::assertFalse($source->isInteractive());
    }

    #[Test]
    public function acceptsNonSequentialKeys(): void
    {
        // array_values() normalisation — keys must not matter.
        $source = new StringQueueStdinSource([5 => 'a', 10 => 'b']);

        self::assertSame('a', $source->readLine());
        self::assertSame('b', $source->readLine());
        self::assertNull($source->readLine());
    }
}

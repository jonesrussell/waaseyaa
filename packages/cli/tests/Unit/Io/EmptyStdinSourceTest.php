<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Io;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Io\EmptyStdinSource;

#[CoversClass(EmptyStdinSource::class)]
final class EmptyStdinSourceTest extends TestCase
{
    #[Test]
    public function readLineAlwaysReturnsNull(): void
    {
        $source = new EmptyStdinSource();

        self::assertNull($source->readLine());
        self::assertNull($source->readLine());
    }

    #[Test]
    public function isInteractiveReturnsFalse(): void
    {
        $source = new EmptyStdinSource();

        self::assertFalse($source->isInteractive());
    }
}

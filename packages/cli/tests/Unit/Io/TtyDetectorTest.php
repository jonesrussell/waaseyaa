<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Io;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Io\TtyDetector;

#[CoversClass(TtyDetector::class)]
final class TtyDetectorTest extends TestCase
{
    #[Test]
    public function tempFileIsNotInteractive(): void
    {
        // A tmpfile() handle is always a regular file — never a TTY.
        $stream = tmpfile();
        assert(is_resource($stream));

        self::assertFalse(TtyDetector::isInteractive($stream));

        fclose($stream);
    }

    #[Test]
    public function phpMemoryStreamIsNotInteractive(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));

        self::assertFalse(TtyDetector::isInteractive($stream));

        fclose($stream);
    }

    #[Test]
    public function returnsConsistentResultForSameStream(): void
    {
        $stream = tmpfile();
        assert(is_resource($stream));

        $first  = TtyDetector::isInteractive($stream);
        $second = TtyDetector::isInteractive($stream);

        self::assertSame($first, $second);

        fclose($stream);
    }
}

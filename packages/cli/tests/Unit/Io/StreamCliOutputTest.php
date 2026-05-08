<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Io;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Io\StreamCliOutput;

#[CoversClass(StreamCliOutput::class)]
final class StreamCliOutputTest extends TestCase
{
    #[Test]
    public function writeToStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));

        $output = new StreamCliOutput($stream);
        $output->write('hello');

        rewind($stream);
        self::assertSame('hello', stream_get_contents($stream));
        fclose($stream);
    }

    #[Test]
    public function writelnAppendsNewline(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));

        $output = new StreamCliOutput($stream);
        $output->writeln('line');

        rewind($stream);
        self::assertSame("line\n", stream_get_contents($stream));
        fclose($stream);
    }

    #[Test]
    public function writelnWithNoArgWritesBlankLine(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));

        $output = new StreamCliOutput($stream);
        $output->writeln();

        rewind($stream);
        self::assertSame("\n", stream_get_contents($stream));
        fclose($stream);
    }

    #[Test]
    public function multipleWritesAccumulate(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));

        $output = new StreamCliOutput($stream);
        $output->write('a');
        $output->writeln('b');
        $output->write('c');

        rewind($stream);
        self::assertSame("ab\nc", stream_get_contents($stream));
        fclose($stream);
    }
}

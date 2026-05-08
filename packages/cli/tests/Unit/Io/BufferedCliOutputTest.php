<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Io;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Io\BufferedCliOutput;

#[CoversClass(BufferedCliOutput::class)]
final class BufferedCliOutputTest extends TestCase
{
    #[Test]
    public function itStartsEmpty(): void
    {
        $output = new BufferedCliOutput();

        self::assertSame('', $output->getContents());
    }

    #[Test]
    public function writeAppendsTextWithoutNewline(): void
    {
        $output = new BufferedCliOutput();
        $output->write('hello');
        $output->write(' world');

        self::assertSame('hello world', $output->getContents());
    }

    #[Test]
    public function writelnAppendsTextWithNewline(): void
    {
        $output = new BufferedCliOutput();
        $output->writeln('line one');
        $output->writeln('line two');

        self::assertSame("line one\nline two\n", $output->getContents());
    }

    #[Test]
    public function writelnWithNoArgAppendsBlankLine(): void
    {
        $output = new BufferedCliOutput();
        $output->writeln();

        self::assertSame("\n", $output->getContents());
    }

    #[Test]
    public function mixedWriteAndWriteln(): void
    {
        $output = new BufferedCliOutput();
        $output->write('a');
        $output->writeln('b');
        $output->write('c');

        self::assertSame("ab\nc", $output->getContents());
    }

    #[Test]
    public function resetClearsBuffer(): void
    {
        $output = new BufferedCliOutput();
        $output->writeln('some text');
        $output->reset();

        self::assertSame('', $output->getContents());
    }

    #[Test]
    public function capturesExactBytes(): void
    {
        $output = new BufferedCliOutput();
        $text   = "line\x00with\tnull\xFFbytes";
        $output->write($text);

        self::assertSame($text, $output->getContents());
    }
}

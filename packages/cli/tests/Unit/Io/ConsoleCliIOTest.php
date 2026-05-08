<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Io;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\ConsoleCliIO;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\Io\StdinSource;
use Waaseyaa\CLI\Io\StringQueueStdinSource;
use Waaseyaa\CLI\Parser\ParsedInput;

#[CoversClass(ConsoleCliIO::class)]
final class ConsoleCliIOTest extends TestCase
{
    private function makeIo(
        array $arguments = [],
        array $options = [],
        ?BufferedCliOutput $stdout = null,
        ?BufferedCliOutput $stderr = null,
        bool $verbose = false,
        bool $interactive = false,
        array $stdinLines = [],
    ): ConsoleCliIO {
        $stdinSource = $interactive
            ? new StringQueueStdinSource($stdinLines)
            : new EmptyStdinSource();

        return new ConsoleCliIO(
            input:   new ParsedInput(arguments: $arguments, options: $options, rawArgv: []),
            stdout:  $stdout ?? new BufferedCliOutput(),
            stderr:  $stderr ?? new BufferedCliOutput(),
            stdin:   $stdinSource,
            verbose: $verbose,
        );
    }

    // --- Argument access ---

    #[Test]
    public function argumentReturnsValue(): void
    {
        $io = $this->makeIo(arguments: ['name' => 'alice']);

        self::assertSame('alice', $io->argument('name'));
    }

    #[Test]
    public function argumentReturnsNullForMissing(): void
    {
        $io = $this->makeIo();

        self::assertNull($io->argument('missing'));
    }

    #[Test]
    public function argumentsReturnsAll(): void
    {
        $io = $this->makeIo(arguments: ['a' => '1', 'b' => '2']);

        self::assertSame(['a' => '1', 'b' => '2'], $io->arguments());
    }

    // --- Option access ---

    #[Test]
    public function optionReturnsValue(): void
    {
        $io = $this->makeIo(options: ['shout' => true]);

        self::assertTrue($io->option('shout'));
    }

    #[Test]
    public function optionReturnsNullForMissing(): void
    {
        $io = $this->makeIo();

        self::assertNull($io->option('missing'));
    }

    #[Test]
    public function optionsReturnsAll(): void
    {
        $io = $this->makeIo(options: ['x' => 'v', 'y' => false]);

        self::assertSame(['x' => 'v', 'y' => false], $io->options());
    }

    // --- Output routing ---

    #[Test]
    public function writelnGoesToStdout(): void
    {
        $stdout = new BufferedCliOutput();
        $stderr = new BufferedCliOutput();
        $io     = $this->makeIo(stdout: $stdout, stderr: $stderr);

        $io->writeln('hello');

        self::assertSame("hello\n", $stdout->getContents());
        self::assertSame('', $stderr->getContents());
    }

    #[Test]
    public function writeGoesToStdout(): void
    {
        $stdout = new BufferedCliOutput();
        $io     = $this->makeIo(stdout: $stdout);

        $io->write('raw');

        self::assertSame('raw', $stdout->getContents());
    }

    #[Test]
    public function errorGoesToStderr(): void
    {
        $stdout = new BufferedCliOutput();
        $stderr = new BufferedCliOutput();
        $io     = $this->makeIo(stdout: $stdout, stderr: $stderr);

        $io->error('oops');

        self::assertSame('', $stdout->getContents());
        self::assertSame("oops\n", $stderr->getContents());
    }

    // --- ask / confirm (non-interactive) ---

    #[Test]
    public function askNonInteractiveReturnsDefault(): void
    {
        $stderr = new BufferedCliOutput();
        $io     = $this->makeIo(stderr: $stderr, interactive: false);

        $result = $io->ask('What is your name?', 'anonymous');

        self::assertSame('anonymous', $result);
        self::assertStringContainsString('What is your name?', $stderr->getContents());
        self::assertStringContainsString('not a tty', $stderr->getContents());
    }

    #[Test]
    public function askNonInteractiveWithNullDefaultReturnsNull(): void
    {
        $io = $this->makeIo(interactive: false);

        self::assertNull($io->ask('Q?', null));
    }

    #[Test]
    public function confirmNonInteractiveReturnsDefault(): void
    {
        $stderr = new BufferedCliOutput();
        $io     = $this->makeIo(stderr: $stderr, interactive: false);

        self::assertFalse($io->confirm('Continue?', false));
        self::assertTrue($io->confirm('Continue?', true));
    }

    // --- ask / confirm (interactive via StringQueueStdinSource) ---

    private function interactiveStdin(array $lines): StdinSource
    {
        return new class ($lines) implements StdinSource {
            /** @var list<string> */
            private array $queue;

            public function __construct(array $lines) { $this->queue = array_values($lines); }

            public function readLine(): ?string
            {
                return $this->queue !== [] ? array_shift($this->queue) : null;
            }

            public function isInteractive(): bool { return true; }
        };
    }

    #[Test]
    public function askInteractiveReturnsAnswer(): void
    {
        $stderr = new BufferedCliOutput();
        $io     = new ConsoleCliIO(
            input:   new ParsedInput(arguments: [], options: [], rawArgv: []),
            stdout:  new BufferedCliOutput(),
            stderr:  $stderr,
            stdin:   $this->interactiveStdin(['alice']),
            verbose: false,
        );

        self::assertSame('alice', $io->ask('Name?'));
    }

    #[Test]
    public function confirmInteractiveAcceptsYesVariants(): void
    {
        foreach (['y', 'yes', 'Y', 'YES'] as $answer) {
            $io = new ConsoleCliIO(
                input:   new ParsedInput(arguments: [], options: [], rawArgv: []),
                stdout:  new BufferedCliOutput(),
                stderr:  new BufferedCliOutput(),
                stdin:   $this->interactiveStdin([$answer]),
                verbose: false,
            );

            self::assertTrue($io->confirm('OK?', false), "Expected true for answer '$answer'");
        }
    }

    #[Test]
    public function confirmInteractiveAcceptsNoVariants(): void
    {
        foreach (['n', 'no', 'N', 'NO'] as $answer) {
            $io = new ConsoleCliIO(
                input:   new ParsedInput(arguments: [], options: [], rawArgv: []),
                stdout:  new BufferedCliOutput(),
                stderr:  new BufferedCliOutput(),
                stdin:   $this->interactiveStdin([$answer]),
                verbose: false,
            );

            self::assertFalse($io->confirm('OK?', true), "Expected false for answer '$answer'");
        }
    }

    #[Test]
    public function confirmInteractiveGarbageAnswerReturnsDefault(): void
    {
        $io = new ConsoleCliIO(
            input:   new ParsedInput(arguments: [], options: [], rawArgv: []),
            stdout:  new BufferedCliOutput(),
            stderr:  new BufferedCliOutput(),
            stdin:   $this->interactiveStdin(['maybe']),
            verbose: false,
        );

        self::assertTrue($io->confirm('OK?', true));
    }

    #[Test]
    public function askInteractiveEofReturnsDefault(): void
    {
        $io = new ConsoleCliIO(
            input:   new ParsedInput(arguments: [], options: [], rawArgv: []),
            stdout:  new BufferedCliOutput(),
            stderr:  new BufferedCliOutput(),
            stdin:   $this->interactiveStdin([]),
            verbose: false,
        );

        self::assertSame('fallback', $io->ask('Name?', 'fallback'));
    }

    // --- Flags ---

    #[Test]
    public function isVerboseReflectsVerboseParam(): void
    {
        $quiet   = $this->makeIo(verbose: false);
        $verbose = $this->makeIo(verbose: true);

        self::assertFalse($quiet->isVerbose());
        self::assertTrue($verbose->isVerbose());
    }

    #[Test]
    public function isInteractiveReflectsStdinSource(): void
    {
        $nonInteractive = $this->makeIo(interactive: false);

        self::assertFalse($nonInteractive->isInteractive());
    }
}

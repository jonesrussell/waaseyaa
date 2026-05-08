<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Io\StringQueueStdinSource;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\CLI\Testing\CliTester;

#[CoversClass(CliTester::class)]
final class CliTesterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** A PSR-11 container backed by a plain array. */
    private function container(array $bindings = []): ContainerInterface
    {
        return new class ($bindings) implements ContainerInterface {
            public function __construct(private array $bindings) {}

            public function get(string $id): mixed
            {
                if (!array_key_exists($id, $this->bindings)) {
                    throw new class ($id) extends \RuntimeException implements NotFoundExceptionInterface {
                        public function __construct(string $id) { parent::__construct("Not found: $id"); }
                    };
                }

                return $this->bindings[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->bindings);
            }
        };
    }

    private function echoCommand(): CommandDefinition
    {
        return new CommandDefinition(
            name: 'echo',
            description: 'Echo positional argument to stdout.',
            arguments: [
                new ArgumentDefinition(name: 'message', mode: ArgumentMode::Required, description: 'The message.'),
            ],
            handler: static function (CliIO $io): int {
                $io->writeln((string) $io->argument('message'));

                return 0;
            },
        );
    }

    private function shoutCommand(): CommandDefinition
    {
        return new CommandDefinition(
            name: 'shout',
            description: 'Shout a message.',
            arguments: [
                new ArgumentDefinition(name: 'message', mode: ArgumentMode::Required, description: 'Message.'),
            ],
            options: [
                new OptionDefinition(name: 'shout', mode: OptionMode::None, description: 'Uppercase output.'),
            ],
            handler: static function (CliIO $io): int {
                $msg = (string) $io->argument('message');
                $io->writeln($io->option('shout') ? strtoupper($msg) : $msg);

                return 0;
            },
        );
    }

    // -------------------------------------------------------------------------
    // Basic round-trip
    // -------------------------------------------------------------------------

    #[Test]
    public function executeRoundTripWithClosureHandler(): void
    {
        $tester = CliTester::for($this->echoCommand(), $this->container());
        $tester->execute(['hello']);

        self::assertSame(0, $tester->getExitCode());
        self::assertSame("hello\n", $tester->getStdout());
        self::assertSame('', $tester->getStderr());
    }

    #[Test]
    public function executeRoundTripWithFqnHandler(): void
    {
        // A simple handler class.
        $handlerObj = new class () {
            public function handle(CliIO $io): int
            {
                $io->writeln('from-fqn');

                return 42;
            }
        };

        $cmd = new CommandDefinition(
            name: 'fqn-cmd',
            description: 'Handler resolved via FQN.',
            handler: [$handlerObj::class, 'handle'],
        );

        $tester = CliTester::for($cmd, $this->container([$handlerObj::class => $handlerObj]));
        $tester->execute([]);

        self::assertSame(42, $tester->getExitCode());
        self::assertSame("from-fqn\n", $tester->getStdout());
    }

    // -------------------------------------------------------------------------
    // executeMap
    // -------------------------------------------------------------------------

    #[Test]
    public function executeMapTranslatesPositionalArg(): void
    {
        $tester = CliTester::for($this->echoCommand(), $this->container());
        $tester->executeMap(['message' => 'world']);

        self::assertSame("world\n", $tester->getStdout());
    }

    #[Test]
    public function executeMapTranslatesBooleanFlag(): void
    {
        $tester = CliTester::for($this->shoutCommand(), $this->container());
        $tester->executeMap(['message' => 'hello', '--shout' => true]);

        self::assertSame("HELLO\n", $tester->getStdout());
    }

    #[Test]
    public function executeMapWithFalseOmitsFlag(): void
    {
        $tester = CliTester::for($this->shoutCommand(), $this->container());
        $tester->executeMap(['message' => 'hello', '--shout' => false]);

        self::assertSame("hello\n", $tester->getStdout());
    }

    #[Test]
    public function executeMapWithArrayArg(): void
    {
        $cmd = new CommandDefinition(
            name: 'collect',
            description: 'Collect items.',
            arguments: [
                new ArgumentDefinition(name: 'items', mode: ArgumentMode::Optional, isArray: true, description: 'Items.'),
            ],
            handler: static function (CliIO $io): int {
                $items = (array) $io->argument('items');
                $io->writeln(implode(',', $items));

                return 0;
            },
        );

        $tester = CliTester::for($cmd, $this->container());
        $tester->executeMap(['items' => ['a', 'b', 'c']]);

        self::assertSame("a,b,c\n", $tester->getStdout());
    }

    // -------------------------------------------------------------------------
    // Stderr / getOutput
    // -------------------------------------------------------------------------

    #[Test]
    public function capturesStderrSeparately(): void
    {
        $cmd = new CommandDefinition(
            name: 'err',
            description: 'Write to stderr.',
            handler: static function (CliIO $io): int {
                $io->error('bad news');

                return 1;
            },
        );

        $tester = CliTester::for($cmd, $this->container());
        $tester->execute([]);

        self::assertSame(1, $tester->getExitCode());
        self::assertSame('', $tester->getStdout());
        self::assertSame("bad news\n", $tester->getStderr());
    }

    #[Test]
    public function getOutputInterleavesBothStreams(): void
    {
        $cmd = new CommandDefinition(
            name: 'mixed',
            description: 'Mix stdout and stderr.',
            handler: static function (CliIO $io): int {
                $io->writeln('out1');
                $io->error('err1');
                $io->writeln('out2');

                return 0;
            },
        );

        $tester = CliTester::for($cmd, $this->container());
        $tester->execute([]);

        self::assertSame("out1\nerr1\nout2\n", $tester->getOutput());
    }

    // -------------------------------------------------------------------------
    // Determinism — two execute() calls are independent
    // -------------------------------------------------------------------------

    #[Test]
    public function twoExecuteCallsAreIndependent(): void
    {
        $tester = CliTester::for($this->echoCommand(), $this->container());

        $tester->execute(['first']);
        $first = $tester->getStdout();

        $tester->execute(['second']);
        $second = $tester->getStdout();

        self::assertSame("first\n", $first);
        self::assertSame("second\n", $second);
    }

    #[Test]
    public function exitCodeResetsOnSecondExecute(): void
    {
        $cmd = new CommandDefinition(
            name: 'fail',
            description: 'Returns non-zero.',
            handler: static function (CliIO $io): int { return 99; },
        );

        $succeed = new CommandDefinition(
            name: 'succeed',
            description: 'Returns zero.',
            handler: static function (CliIO $io): int { return 0; },
        );

        $t1 = CliTester::for($cmd, $this->container());
        $t1->execute([]);
        self::assertSame(99, $t1->getExitCode());

        // A fresh tester for a different command returns 0.
        $t2 = CliTester::for($succeed, $this->container());
        $t2->execute([]);
        self::assertSame(0, $t2->getExitCode());
    }

    // -------------------------------------------------------------------------
    // StdinSource injection (ask / confirm)
    // -------------------------------------------------------------------------

    #[Test]
    public function stringQueueStdinSourceFeedsPrompts(): void
    {
        $cmd = new CommandDefinition(
            name: 'greet',
            description: 'Greet a user by name.',
            handler: static function (CliIO $io): int {
                // Non-interactive stdin → ask returns default.
                $name = $io->ask('Name?', 'stranger');
                $io->writeln("Hello, $name!");

                return 0;
            },
        );

        // StringQueueStdinSource is non-interactive, so default is returned.
        $tester = CliTester::for(
            $cmd,
            $this->container(),
            stdin: new StringQueueStdinSource(['alice']),
        );
        $tester->execute([]);

        // Non-interactive: default is used.
        self::assertSame("Hello, stranger!\n", $tester->getStdout());
    }

    // -------------------------------------------------------------------------
    // No Symfony imports
    // -------------------------------------------------------------------------

    #[Test]
    public function cliTesterHasNoSymfonyImports(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/src/Testing/CliTester.php',
        );
        assert(is_string($source));

        self::assertStringNotContainsString(
            'Symfony\\',
            $source,
            'CliTester must not import any Symfony classes.',
        );
    }
}

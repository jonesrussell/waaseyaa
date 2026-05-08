<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Handler\EventListHandler;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\Provider\MiscAServiceProvider;

/**
 * Snapshot test: verifies event:list --help output matches the fixture byte-for-byte.
 */
#[CoversNothing]
final class EventListSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $provider = new MiscAServiceProvider();
        foreach ($provider->nativeCommands() as $cmd) {
            if ($cmd->name === 'event:list') {
                $this->registry->register($cmd);
            }
        }
    }

    #[Test]
    public function helpOutputMatchesFixtureByteForByte(): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/event__list.help.stdout',
        );
        self::assertNotFalse($fixture, 'Fixture file must exist');

        [$stdout, $exitCode] = $this->runHelp('event:list');

        self::assertSame(0, $exitCode, 'event:list --help must exit 0');
        self::assertSame($fixture, $stdout, 'event:list --help output must match fixture byte-for-byte');
    }

    /**
     * @return array{string, int}
     */
    private function runHelp(string $commandName): array
    {
        $stdout = new BufferedCliOutput();
        $stderr = new BufferedCliOutput();

        $dispatcher = new EventDispatcher();
        $container = new class ($dispatcher) implements \Psr\Container\ContainerInterface {
            public function __construct(private readonly EventDispatcher $d) {}

            public function get(string $id): mixed
            {
                if ($id === EventListHandler::class) {
                    return new EventListHandler($this->d);
                }

                throw new \RuntimeException(sprintf('Container::get(%s) called in snapshot test', $id));
            }

            public function has(string $id): bool
            {
                return $id === EventListHandler::class;
            }
        };

        $kernel = new CliKernel(
            registry: $this->registry,
            container: $container,
            help: new HelpRenderer(),
            stdout: $stdout,
            stderr: $stderr,
            stdin: new EmptyStdinSource(),
        );

        $exitCode = $kernel->run([$commandName, '--help']);

        return [$stdout->getContents(), $exitCode];
    }
}

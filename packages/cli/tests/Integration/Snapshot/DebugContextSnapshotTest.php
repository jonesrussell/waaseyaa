<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Handler\DebugContextHandler;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\Provider\MiscAServiceProvider;

/**
 * Snapshot test: verifies debug:context --help output matches the fixture byte-for-byte.
 */
#[CoversNothing]
final class DebugContextSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $provider = new MiscAServiceProvider();
        foreach ($provider->nativeCommands() as $cmd) {
            if ($cmd->name === 'debug:context') {
                $this->registry->register($cmd);
            }
        }
    }

    #[Test]
    public function helpOutputMatchesFixtureByteForByte(): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/debug__context.help.stdout',
        );
        self::assertNotFalse($fixture, 'Fixture file must exist');

        [$stdout, $exitCode] = $this->runHelp('debug:context');

        self::assertSame(0, $exitCode, 'debug:context --help must exit 0');
        self::assertSame($fixture, $stdout, 'debug:context --help output must match fixture byte-for-byte');
    }

    /**
     * @return array{string, int}
     */
    private function runHelp(string $commandName): array
    {
        $stdout = new BufferedCliOutput();
        $stderr = new BufferedCliOutput();

        $container = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id): mixed
            {
                if ($id === DebugContextHandler::class) {
                    return new DebugContextHandler();
                }

                throw new \RuntimeException(sprintf('Container::get(%s) called in snapshot test', $id));
            }

            public function has(string $id): bool
            {
                return $id === DebugContextHandler::class;
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

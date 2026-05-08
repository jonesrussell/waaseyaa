<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\Provider\MakeServiceProviderB;

/**
 * Snapshot test: verifies make:provider --help output matches the fixture byte-for-byte.
 */
#[CoversNothing]
final class MakeProviderSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $provider = new MakeServiceProviderB();
        foreach ($provider->nativeCommands() as $cmd) {
            $this->registry->register($cmd);
        }
    }

    #[Test]
    public function helpOutputMatchesFixtureByteForByte(): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/make__provider.help.stdout',
        );
        self::assertNotFalse($fixture, 'Fixture file must exist');

        [$stdout, $exitCode] = $this->runHelp('make:provider');

        self::assertSame(0, $exitCode, 'make:provider --help must exit 0');
        self::assertSame($fixture, $stdout, 'make:provider --help output must match fixture byte-for-byte');
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
                throw new \RuntimeException(sprintf('Container::get(%s) called in snapshot test', $id));
            }

            public function has(string $id): bool
            {
                return false;
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

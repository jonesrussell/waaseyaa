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
use Waaseyaa\CLI\Provider\TelescopeServiceProvider;

/**
 * Snapshot test: verifies telescope:prune --help output matches the WP01 fixture byte-for-byte.
 */
#[CoversNothing]
final class TelescopePruneSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $provider = new TelescopeServiceProvider();
        foreach ($provider->nativeCommands() as $cmd) {
            $this->registry->register($cmd);
        }
    }

    #[Test]
    public function helpOutputMatchesFixtureByteForByte(): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/telescope__prune.help.stdout',
        );
        self::assertNotFalse($fixture, 'Fixture file must exist');

        [$stdout, $exitCode] = $this->runHelp('telescope:prune');

        self::assertSame(0, $exitCode, 'telescope:prune --help must exit 0');
        self::assertSame($fixture, $stdout, 'telescope:prune --help output must match fixture byte-for-byte');
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
                throw new \RuntimeException('Container not wired in snapshot tests');
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

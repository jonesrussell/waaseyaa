<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Handler\WaaseyaaVersionHandler;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;

/**
 * Snapshot test: verifies waaseyaa:version --help output matches the fixture byte-for-byte.
 */
#[CoversNothing]
final class WaaseyaaVersionSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();

        $handler = new WaaseyaaVersionHandler(projectRoot: '/tmp');

        $this->registry->register(new CommandDefinition(
            name: 'waaseyaa:version',
            description: 'Print waaseyaa/* framework provenance (path SHA, lockfile versions, drift vs golden SHA)',
            options: [
                new OptionDefinition(name: 'json', mode: OptionMode::None, description: 'Machine-readable JSON'),
                new OptionDefinition(name: 'strict', mode: OptionMode::None, description: 'Fail on drift when golden SHA is set (same as default; omit --report-only)'),
                new OptionDefinition(name: 'report-only', mode: OptionMode::None, description: 'Print drift but always exit 0'),
            ],
            handler: \Closure::fromCallable([$handler, 'execute']),
        ));
    }

    #[Test]
    public function helpOutputMatchesFixtureByteForByte(): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/waaseyaa__version.help.stdout',
        );
        self::assertNotFalse($fixture, 'Fixture file must exist');

        [$stdout, $exitCode] = $this->runHelp('waaseyaa:version');

        self::assertSame(0, $exitCode, 'waaseyaa:version --help must exit 0');
        self::assertSame($fixture, $stdout, 'waaseyaa:version --help output must match fixture byte-for-byte');
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

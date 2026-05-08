<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Handler\ServeHandler;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;

/**
 * Snapshot test: verifies serve --help output matches the fixture byte-for-byte.
 */
#[CoversNothing]
final class ServeSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();

        $handler = new ServeHandler(projectRoot: '/tmp');

        $this->registry->register(new CommandDefinition(
            name: 'serve',
            description: 'Start the PHP development server',
            options: [
                new OptionDefinition(
                    name: 'host',
                    mode: OptionMode::Optional,
                    description: 'Specify which IP address the server should listen on. Set to 127.0.0.1 to restrict to localhost only. Can also be set via APP_HOST.',
                    default: '0.0.0.0',
                ),
                new OptionDefinition(
                    name: 'port',
                    shortcut: 'p',
                    mode: OptionMode::Optional,
                    description: 'Specify which port the server should listen on. Can also be set via APP_PORT.',
                    default: '8080',
                ),
            ],
            handler: \Closure::fromCallable([$handler, 'execute']),
        ));
    }

    #[Test]
    public function helpOutputMatchesFixtureByteForByte(): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/serve.help.stdout',
        );
        self::assertNotFalse($fixture, 'Fixture file must exist');

        [$stdout, $exitCode] = $this->runHelp('serve');

        self::assertSame(0, $exitCode, 'serve --help must exit 0');
        self::assertSame($fixture, $stdout, 'serve --help output must match fixture byte-for-byte');
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

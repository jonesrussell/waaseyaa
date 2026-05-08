<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Handler\RouteListHandler;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Snapshot test: verifies route:list --help output matches the fixture byte-for-byte.
 */
#[CoversNothing]
final class RouteListSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();

        $router = new WaaseyaaRouter();
        $handler = new RouteListHandler(router: $router);

        $this->registry->register(new CommandDefinition(
            name: 'route:list',
            description: 'List all registered routes',
            options: [
                new OptionDefinition(name: 'path', mode: OptionMode::Required, description: 'Filter routes by path pattern'),
            ],
            handler: \Closure::fromCallable([$handler, 'execute']),
        ));
    }

    #[Test]
    public function helpOutputMatchesFixtureByteForByte(): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/route__list.help.stdout',
        );
        self::assertNotFalse($fixture, 'Fixture file must exist');

        [$stdout, $exitCode] = $this->runHelp('route:list');

        self::assertSame(0, $exitCode, 'route:list --help must exit 0');
        self::assertSame($fixture, $stdout, 'route:list --help output must match fixture byte-for-byte');
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

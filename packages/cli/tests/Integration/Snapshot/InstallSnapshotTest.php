<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Handler\InstallHandler;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Config\ConfigManagerInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;

/**
 * Snapshot test: verifies install --help output matches the fixture byte-for-byte.
 */
#[CoversNothing]
final class InstallSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();

        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $configManager = $this->createMock(ConfigManagerInterface::class);

        $handler = new InstallHandler(
            entityTypeManager: $entityTypeManager,
            configManager: $configManager,
        );

        $this->registry->register(new CommandDefinition(
            name: 'install',
            description: 'Install Waaseyaa with initial configuration',
            options: [
                new OptionDefinition(name: 'site-name', mode: OptionMode::Required, description: 'The name of the site', default: 'Waaseyaa'),
                new OptionDefinition(name: 'site-mail', mode: OptionMode::Required, description: 'Site email address', default: 'admin@example.com'),
                new OptionDefinition(name: 'admin-email', mode: OptionMode::Required, description: 'Admin user email', default: 'admin@example.com'),
                new OptionDefinition(name: 'admin-password', mode: OptionMode::Required, description: 'Admin user password'),
            ],
            handler: \Closure::fromCallable([$handler, 'execute']),
        ));
    }

    #[Test]
    public function helpOutputMatchesFixtureByteForByte(): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/install.help.stdout',
        );
        self::assertNotFalse($fixture, 'Fixture file must exist');

        [$stdout, $exitCode] = $this->runHelp('install');

        self::assertSame(0, $exitCode, 'install --help must exit 0');
        self::assertSame($fixture, $stdout, 'install --help output must match fixture byte-for-byte');
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

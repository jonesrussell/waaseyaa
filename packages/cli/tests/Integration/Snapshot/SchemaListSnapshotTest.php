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
use Waaseyaa\CLI\Provider\HealthSchemaServiceProvider;

/**
 * Snapshot test: verifies schema:list --help output is stable.
 */
#[CoversNothing]
final class SchemaListSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $provider = new HealthSchemaServiceProvider();
        foreach ($provider->nativeCommands() as $cmd) {
            $this->registry->register($cmd);
        }
    }

    #[Test]
    public function helpOutputContainsCommandMetadata(): void
    {
        [$stdout, $exitCode] = $this->runHelp('schema:list');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('schema:list', $stdout);
        self::assertStringContainsString('List registered schemas with versions and compatibility policy', $stdout);
        self::assertStringContainsString('--help', $stdout);
    }

    #[Test]
    public function helpExitCodeIsZero(): void
    {
        [, $exitCode] = $this->runHelp('schema:list');
        self::assertSame(0, $exitCode);
    }

    #[Test]
    public function helpHasNoJsonOption(): void
    {
        [$stdout] = $this->runHelp('schema:list');
        // schema:list has no --json option; only kernel-level options should appear
        self::assertStringNotContainsString('Output results as JSON', $stdout);
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
                throw new \RuntimeException(sprintf('Container::get(%s) called unexpectedly', $id));
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

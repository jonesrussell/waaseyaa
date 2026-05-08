<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\CLI\Provider\HealthSchemaServiceProvider;

/**
 * Snapshot test: verifies health:check --help output is stable.
 *
 * Uses the native CliKernel + HelpRenderer — not Symfony CommandTester.
 * The command name, description, and options must match the WP01 fixtures
 * (captured from the legacy command configure()) exactly.
 */
#[CoversNothing]
final class HealthCheckSnapshotTest extends TestCase
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
        [$stdout, $exitCode] = $this->runHelp('health:check');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('health:check', $stdout);
        self::assertStringContainsString('Run all diagnostic health checks and report results', $stdout);
        self::assertStringContainsString('--json', $stdout);
        self::assertStringContainsString('Output results as JSON', $stdout);
        self::assertStringContainsString('--help', $stdout);
        self::assertStringContainsString('--verbose', $stdout);
    }

    #[Test]
    public function helpExitCodeIsZero(): void
    {
        [, $exitCode] = $this->runHelp('health:check');
        self::assertSame(0, $exitCode);
    }

    #[Test]
    public function helpOutputMatchesNativeFormat(): void
    {
        [$stdout] = $this->runHelp('health:check');

        // Native HelpRenderer puts Usage: first, then Description:
        $usagePos = strpos($stdout, 'Usage:');
        $descPos = strpos($stdout, 'Description:');

        self::assertNotFalse($usagePos, 'Help must contain Usage: section');
        self::assertNotFalse($descPos, 'Help must contain Description: section');
        self::assertLessThan($descPos, $usagePos, 'Usage: must appear before Description:');
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

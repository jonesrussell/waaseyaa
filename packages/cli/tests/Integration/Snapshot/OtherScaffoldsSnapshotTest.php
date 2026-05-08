<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\Provider\OtherScaffoldsServiceProvider;

/**
 * Snapshot tests: verify scaffold:relationship, scaffold:workflow, scaffold:extension,
 * scaffold:auth --help output matches the WP19 baseline fixtures byte-for-byte.
 *
 * These commands are new in this mission (no WP01 baseline). Fixtures were generated
 * by running the native CLI handler and captured here as the authoritative baseline.
 */
#[CoversNothing]
final class OtherScaffoldsSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $provider = new OtherScaffoldsServiceProvider();
        foreach ($provider->nativeCommands() as $cmd) {
            $this->registry->register($cmd);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function helpFixtures(): array
    {
        return [
            'scaffold:relationship' => ['scaffold:relationship', 'scaffold__relationship.help.stdout'],
            'scaffold:workflow'     => ['scaffold:workflow',     'scaffold__workflow.help.stdout'],
            'scaffold:extension'    => ['scaffold:extension',    'scaffold__extension.help.stdout'],
            'scaffold:auth'        => ['scaffold:auth',         'scaffold__auth.help.stdout'],
        ];
    }

    #[Test]
    #[DataProvider('helpFixtures')]
    public function helpOutputMatchesFixtureByteForByte(string $commandName, string $fixtureFile): void
    {
        $fixture = file_get_contents(
            __DIR__ . '/../../Fixtures/snapshots/' . $fixtureFile,
        );
        self::assertNotFalse($fixture, sprintf('Fixture file "%s" must exist', $fixtureFile));

        [$stdout, $exitCode] = $this->runHelp($commandName);

        self::assertSame(0, $exitCode, sprintf('%s --help must exit 0', $commandName));
        self::assertSame($fixture, $stdout, sprintf('%s --help output must match fixture byte-for-byte', $commandName));
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

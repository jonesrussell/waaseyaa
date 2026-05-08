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
use Waaseyaa\CLI\Provider\IngestSearchSemanticServiceProvider;

/**
 * Snapshot tests: verify ingest/search/semantic --help output matches the WP01 fixtures byte-for-byte.
 *
 * Covers: ingest:run, ingest:dashboard, search:reindex, semantic:warm, semantic:refresh
 *
 * Note: search:reindex fixture did not exist in the WP01 baseline (a923be435) — it was
 * generated at WP16 port time and serves as the immutable native-renderer baseline.
 */
#[CoversNothing]
final class IngestSearchSemanticSnapshotTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $provider = new IngestSearchSemanticServiceProvider();
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
            'ingest:run'       => ['ingest:run',       'ingest__run.help.stdout'],
            'ingest:dashboard' => ['ingest:dashboard',  'ingest__dashboard.help.stdout'],
            'search:reindex'   => ['search:reindex',    'search__reindex.help.stdout'],
            'semantic:warm'    => ['semantic:warm',     'semantic__warm.help.stdout'],
            'semantic:refresh' => ['semantic:refresh',  'semantic__refresh.help.stdout'],
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

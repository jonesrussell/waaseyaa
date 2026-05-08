<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\CLI\CliApplication;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Compat\LegacySymfonyCommandAdapter;
use Waaseyaa\CLI\Compat\LegacySymfonyCommandRegistrar;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\CLI\Provider\CliKernelServiceProvider;
use Waaseyaa\Cli\CliIO;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DeleteInterface;
use Waaseyaa\Database\InsertInterface;
use Waaseyaa\Database\SchemaInterface;
use Waaseyaa\Database\SelectInterface;
use Waaseyaa\Database\TransactionInterface;
use Waaseyaa\Database\UpdateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Event\EventDispatcherInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;

/**
 * Integration test: dual-boot adapter wires both native and legacy Symfony commands.
 *
 * Verifies that:
 * - Native HasNativeCommandsInterface providers register commands into CliKernel.
 * - Legacy HasCommandsInterface providers are adapted and also registered.
 * - Both command types can be dispatched through CliKernel::run().
 * - The command listing shows both command types.
 */
#[CoversNothing]
final class DualBootTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Fixture helpers
    // -----------------------------------------------------------------------

    private function makeNativeProvider(): HasNativeCommandsInterface
    {
        return new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new CommandDefinition(
                    name: 'native:hello',
                    description: 'Say hello from native provider.',
                    options: [
                        new OptionDefinition(
                            name: 'name',
                            mode: OptionMode::Required,
                            description: 'Name to greet.',
                            default: 'world',
                        ),
                    ],
                    handler: static function (CliIO $io): int {
                        $name = $io->option('name') ?? 'world';
                        $io->writeln('Hello, ' . $name . '!');
                        return 0;
                    },
                );
            }
        };
    }

    private function makeLegacyProvider(): HasCommandsInterface
    {
        return new class implements HasCommandsInterface {
            public function commands(
                EntityTypeManager $entityTypeManager,
                DatabaseInterface $database,
                EventDispatcherInterface $dispatcher,
            ): array {
                $cmd = new class ('legacy:hello') extends SymfonyCommand {
                    protected function configure(): void
                    {
                        $this->setDescription('Say hello from legacy Symfony provider.');
                        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name to greet.', 'world');
                    }

                    protected function execute(InputInterface $input, OutputInterface $output): int
                    {
                        /** @var string $name */
                        $name = $input->getOption('name') ?? 'world';
                        $output->writeln('Hello, ' . $name . '!');
                        return SymfonyCommand::SUCCESS;
                    }
                };

                return [$cmd];
            }
        };
    }

    private function makeContainer(array $bindings = []): ContainerInterface
    {
        return new class ($bindings) implements ContainerInterface {
            public function __construct(private array $bindings) {}

            public function get(string $id): mixed
            {
                if (!isset($this->bindings[$id])) {
                    throw new \RuntimeException("Not found: {$id}");
                }
                return $this->bindings[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->bindings[$id]);
            }
        };
    }

    private function makeDispatcher(): EventDispatcherInterface
    {
        // SymfonyEventDispatcherAdapter satisfies both:
        // - Waaseyaa\Foundation\Event\EventDispatcherInterface (HasCommandsInterface, LegacySymfonyCommandRegistrar)
        // - Symfony\Contracts\EventDispatcher\EventDispatcherInterface (EntityTypeManager)
        return new \Waaseyaa\Foundation\Event\SymfonyEventDispatcherAdapter(
            new \Symfony\Component\EventDispatcher\EventDispatcher(),
        );
    }

    private function makeEntityTypeManager(): EntityTypeManager
    {
        return new EntityTypeManager(
            eventDispatcher: $this->makeDispatcher(),
        );
    }

    private function makeDatabase(): DatabaseInterface
    {
        return new class implements DatabaseInterface {
            public function select(string $table, string $alias = ''): SelectInterface
            {
                throw new \RuntimeException('Not implemented in stub');
            }

            public function insert(string $table): InsertInterface
            {
                throw new \RuntimeException('Not implemented in stub');
            }

            public function update(string $table): UpdateInterface
            {
                throw new \RuntimeException('Not implemented in stub');
            }

            public function delete(string $table): DeleteInterface
            {
                throw new \RuntimeException('Not implemented in stub');
            }

            public function schema(): SchemaInterface
            {
                throw new \RuntimeException('Not implemented in stub');
            }

            public function transaction(string $name = ''): TransactionInterface
            {
                throw new \RuntimeException('Not implemented in stub');
            }

            public function query(string $sql, array $args = []): \Traversable
            {
                return new \ArrayIterator([]);
            }

            public function quoteIdentifier(string $identifier): string
            {
                return '"' . $identifier . '"';
            }
        };
    }

    /**
     * @param list<object> $providers
     */
    private function buildKernel(array $providers): array
    {
        $container = $this->makeContainer();
        $manifest = new PackageManifest();
        $stdout = new BufferedCliOutput();
        $stderr = new BufferedCliOutput();

        $registry = CliKernelServiceProvider::buildRegistry(
            manifest: $manifest,
            container: $container,
            providerInstances: $providers,
        );

        // Register legacy commands from HasCommandsInterface providers.
        $hasLegacy = array_filter($providers, static fn (object $p) => $p instanceof HasCommandsInterface);
        if ($hasLegacy !== []) {
            LegacySymfonyCommandRegistrar::registerAll(
                registry: $registry,
                providers: $providers,
                entityTypeManager: $this->makeEntityTypeManager(),
                database: $this->makeDatabase(),
                dispatcher: $this->makeDispatcher(),
            );
        }

        $kernel = new CliKernel(
            registry: $registry,
            container: $container,
            help: new HelpRenderer(),
            stdout: $stdout,
            stderr: $stderr,
            stdin: new EmptyStdinSource(),
        );

        return [$kernel, $stdout, $stderr];
    }

    // -----------------------------------------------------------------------
    // T025 — Native provider: command dispatched, output contains name
    // -----------------------------------------------------------------------

    #[Test]
    public function nativeHelloCommandReturnsZeroAndOutputsName(): void
    {
        [$kernel, $stdout] = $this->buildKernel([$this->makeNativeProvider()]);

        $exitCode = $kernel->run(['native:hello', '--name=russell']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('russell', $stdout->getContents());
    }

    // -----------------------------------------------------------------------
    // T025 — Legacy Symfony provider: command adapted and dispatched
    // -----------------------------------------------------------------------

    #[Test]
    public function legacyHelloCommandReturnsZeroAndOutputsName(): void
    {
        [$kernel, $stdout] = $this->buildKernel([
            $this->makeNativeProvider(),
            $this->makeLegacyProvider(),
        ]);

        $exitCode = $kernel->run(['legacy:hello', '--name=russell']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('russell', $stdout->getContents());
    }

    // -----------------------------------------------------------------------
    // T025 — Empty argv: listing shows both commands
    // -----------------------------------------------------------------------

    #[Test]
    public function emptyArgvListsBothNativeAndLegacyCommands(): void
    {
        [$kernel, $stdout] = $this->buildKernel([
            $this->makeNativeProvider(),
            $this->makeLegacyProvider(),
        ]);

        $exitCode = $kernel->run([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('native:hello', $stdout->getContents());
        self::assertStringContainsString('legacy:hello', $stdout->getContents());
    }

    // -----------------------------------------------------------------------
    // T025 — Adapter: LegacySymfonyCommandAdapter converts Symfony → native
    // -----------------------------------------------------------------------

    #[Test]
    public function legacyAdapterConvertsSymfonyCommandToDefinition(): void
    {
        $symfonyCmd = new class ('test:adapted') extends SymfonyCommand {
            protected function configure(): void
            {
                $this->setDescription('An adapted command.');
                $this->addOption('flag', 'f', InputOption::VALUE_NONE, 'A flag option.');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return SymfonyCommand::SUCCESS;
            }
        };

        $definition = LegacySymfonyCommandAdapter::adapt($symfonyCmd);

        self::assertSame('test:adapted', $definition->name);
        self::assertSame('An adapted command.', $definition->description);
        self::assertCount(1, $definition->options);
        self::assertSame('flag', $definition->options[0]->name);
        self::assertSame(OptionMode::None, $definition->options[0]->mode);
        self::assertSame('f', $definition->options[0]->shortcut);
    }

    // -----------------------------------------------------------------------
    // T025 — CliApplication::run() with native provider, listing exits 0
    // -----------------------------------------------------------------------

    #[Test]
    public function cliApplicationRunWithNativeProviderReturnsZeroOnEmptyArgv(): void
    {
        // Use the worktree root which has the real manifest and vendor/.
        $worktreeRoot = dirname(__DIR__, 3);

        $exitCode = CliApplication::run(
            argv: [],
            projectRoot: $worktreeRoot,
            providers: [$this->makeNativeProvider()],
        );

        // Empty argv → listing, exit 0.
        self::assertSame(0, $exitCode);
    }

    // -----------------------------------------------------------------------
    // T025 — Duplicate command (native wins, legacy silently skipped)
    // -----------------------------------------------------------------------

    #[Test]
    public function duplicateLegacyCommandIsSkippedNotThrown(): void
    {
        $nativeProvider = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new CommandDefinition(
                    name: 'shared:cmd',
                    description: 'Native version.',
                    handler: static fn (CliIO $io): int => 0,
                );
            }
        };

        $legacyProvider = new class implements HasCommandsInterface {
            public function commands(
                EntityTypeManager $entityTypeManager,
                DatabaseInterface $database,
                EventDispatcherInterface $dispatcher,
            ): array {
                $cmd = new class ('shared:cmd') extends SymfonyCommand {
                    protected function configure(): void
                    {
                        $this->setDescription('Legacy version — should be skipped.');
                    }

                    protected function execute(InputInterface $input, OutputInterface $output): int
                    {
                        return SymfonyCommand::SUCCESS;
                    }
                };
                return [$cmd];
            }
        };

        $container = $this->makeContainer();
        $manifest = new PackageManifest();

        $registry = CliKernelServiceProvider::buildRegistry(
            manifest: $manifest,
            container: $container,
            providerInstances: [$nativeProvider],
        );

        // Must not throw — duplicate is logged and skipped.
        LegacySymfonyCommandRegistrar::registerAll(
            registry: $registry,
            providers: [$legacyProvider],
            entityTypeManager: $this->makeEntityTypeManager(),
            database: $this->makeDatabase(),
            dispatcher: $this->makeDispatcher(),
        );

        // Native version survives.
        self::assertSame('Native version.', $registry->get('shared:cmd')?->description);
        self::assertCount(1, $registry->all());
    }
}

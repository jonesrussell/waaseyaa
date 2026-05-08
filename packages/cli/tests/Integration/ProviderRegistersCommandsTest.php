<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Provider\CliKernelServiceProvider;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;

/**
 * Integration tests for CliKernelServiceProvider::buildRegistry().
 *
 * Verifies that provider discovery correctly populates the CommandRegistry
 * from HasNativeCommandsInterface implementors.
 */
#[CoversNothing]
final class ProviderRegistersCommandsTest extends TestCase
{
    private function makeContainer(array $bindings): ContainerInterface
    {
        return new class ($bindings) implements ContainerInterface {
            public function __construct(private readonly array $bindings) {}

            public function get(string $id): mixed
            {
                if (!isset($this->bindings[$id])) {
                    throw new class ($id) extends \RuntimeException implements NotFoundExceptionInterface {
                        public function __construct(string $id)
                        {
                            parent::__construct("No entry found for: {$id}");
                        }
                    };
                }
                return $this->bindings[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->bindings[$id]);
            }
        };
    }

    private function makeManifest(array $nativeCommandProviders = []): PackageManifest
    {
        return new PackageManifest(
            providers: $nativeCommandProviders,
            nativeCommandProviders: $nativeCommandProviders,
        );
    }

    // -------------------------------------------------------------------------
    // Single provider with 2 commands
    // -------------------------------------------------------------------------

    #[Test]
    public function singleProviderYieldingTwoCommandsRegisteredBoth(): void
    {
        $provider = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new CommandDefinition(
                    name: 'cmd-a',
                    description: 'Command A.',
                    handler: static fn (CliIO $io): int => 0,
                );
                yield new CommandDefinition(
                    name: 'cmd-b',
                    description: 'Command B.',
                    handler: static fn (CliIO $io): int => 0,
                );
            }
        };

        $manifest = $this->makeManifest([$provider::class]);
        $container = $this->makeContainer([$provider::class => $provider]);

        $registry = CliKernelServiceProvider::buildRegistry($manifest, $container);

        self::assertNotNull($registry->get('cmd-a'));
        self::assertNotNull($registry->get('cmd-b'));
        self::assertCount(2, $registry->all());
    }

    // -------------------------------------------------------------------------
    // Multiple providers
    // -------------------------------------------------------------------------

    #[Test]
    public function multipleProvidersEachContributeCommands(): void
    {
        $providerA = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new CommandDefinition(
                    name: 'provider-a:task',
                    description: 'From provider A.',
                    handler: static fn (CliIO $io): int => 0,
                );
            }
        };

        $providerB = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new CommandDefinition(
                    name: 'provider-b:task',
                    description: 'From provider B.',
                    handler: static fn (CliIO $io): int => 0,
                );
            }
        };

        $manifest = $this->makeManifest([$providerA::class, $providerB::class]);
        $container = $this->makeContainer([
            $providerA::class => $providerA,
            $providerB::class => $providerB,
        ]);

        $registry = CliKernelServiceProvider::buildRegistry($manifest, $container);

        self::assertNotNull($registry->get('provider-a:task'));
        self::assertNotNull($registry->get('provider-b:task'));
        self::assertCount(2, $registry->all());
    }

    // -------------------------------------------------------------------------
    // Provider instances passed directly (bypass manifest)
    // -------------------------------------------------------------------------

    #[Test]
    public function providerInstancesPassedDirectlyBypassManifest(): void
    {
        $provider = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new CommandDefinition(
                    name: 'direct-cmd',
                    description: 'Direct provider.',
                    handler: static fn (CliIO $io): int => 0,
                );
            }
        };

        // Empty manifest — provider injected directly
        $emptyManifest = $this->makeManifest([]);
        $container = $this->makeContainer([]);

        $registry = CliKernelServiceProvider::buildRegistry(
            manifest: $emptyManifest,
            container: $container,
            providerInstances: [$provider],
        );

        self::assertNotNull($registry->get('direct-cmd'));
    }

    // -------------------------------------------------------------------------
    // Unresolvable provider is skipped with a warning (no crash)
    // -------------------------------------------------------------------------

    #[Test]
    public function unresolvableProviderIsSkippedGracefully(): void
    {
        $manifest = $this->makeManifest(['App\\NonExistent\\Provider']);
        $container = $this->makeContainer([]);  // nothing registered

        // Must not throw
        $registry = CliKernelServiceProvider::buildRegistry($manifest, $container);

        self::assertSame([], $registry->all());
    }

    // -------------------------------------------------------------------------
    // Duplicate command from two providers → second silently skipped
    // -------------------------------------------------------------------------

    #[Test]
    public function duplicateCommandFromTwoProvidersSilentlySkipsSecond(): void
    {
        $providerA = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new CommandDefinition(
                    name: 'shared-cmd',
                    description: 'From A.',
                    handler: static fn (CliIO $io): int => 0,
                );
            }
        };

        $providerB = new class implements HasNativeCommandsInterface {
            public function nativeCommands(): iterable
            {
                yield new CommandDefinition(
                    name: 'shared-cmd',
                    description: 'From B.',
                    handler: static fn (CliIO $io): int => 1,
                );
            }
        };

        $registry = CliKernelServiceProvider::buildRegistry(
            manifest: $this->makeManifest([]),
            container: $this->makeContainer([]),
            providerInstances: [$providerA, $providerB],
        );

        // Only first registration survives
        self::assertCount(1, $registry->all());
        self::assertSame('From A.', $registry->get('shared-cmd')?->description);
    }

    // -------------------------------------------------------------------------
    // PackageManifestCompiler detects HasNativeCommandsInterface providers
    // -------------------------------------------------------------------------

    #[Test]
    public function packageManifestHasNativeCommandProvidersField(): void
    {
        $manifest = new PackageManifest(
            providers: ['App\\MyProvider'],
            nativeCommandProviders: ['App\\MyProvider'],
        );

        self::assertSame(['App\\MyProvider'], $manifest->nativeCommandProviders);
    }

    #[Test]
    public function packageManifestNativeCommandProvidersDefaultsToEmpty(): void
    {
        $manifest = new PackageManifest();
        self::assertSame([], $manifest->nativeCommandProviders);
    }

    #[Test]
    public function packageManifestFromArrayWithNativeCommandProviders(): void
    {
        $data = [
            'providers' => ['App\\MyProvider'],
            'migrations' => [],
            'field_types' => [],
            'middleware' => [],
            'native_command_providers' => ['App\\MyProvider'],
        ];

        $manifest = PackageManifest::fromArray($data);
        self::assertSame(['App\\MyProvider'], $manifest->nativeCommandProviders);
    }

    #[Test]
    public function packageManifestFromArrayWithoutNativeCommandProvidersDefaultsToEmpty(): void
    {
        $data = [
            'providers' => [],
            'migrations' => [],
            'field_types' => [],
            'middleware' => [],
        ];

        $manifest = PackageManifest::fromArray($data);
        self::assertSame([], $manifest->nativeCommandProviders);
    }

    #[Test]
    public function packageManifestToArrayIncludesNativeCommandProviders(): void
    {
        $manifest = new PackageManifest(
            providers: ['App\\MyProvider'],
            nativeCommandProviders: ['App\\MyProvider'],
        );

        $arr = $manifest->toArray();
        self::assertArrayHasKey('native_command_providers', $arr);
        self::assertSame(['App\\MyProvider'], $arr['native_command_providers']);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Psr\Container\ContainerInterface;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\StdinSource;
use Waaseyaa\CLI\Io\StreamCliOutput;
use Waaseyaa\CLI\Io\StreamStdinSource;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;

/**
 * Wires `HasNativeCommandsInterface` providers into the `CommandRegistry`
 * and assembles the `CliKernel`.
 *
 * Usage (in bin/waaseyaa or ConsoleKernel):
 *   $registry = CliKernelServiceProvider::buildRegistry($manifest, $container);
 *   $kernel   = CliKernelServiceProvider::buildKernel($registry, $container);
 *   exit($kernel->run(array_slice($_SERVER['argv'], 1)));
 *
 * Full contract: kitty-specs/native-cli-kernel-01KR2NR7/contracts/cli-kernel.md
 */
final class CliKernelServiceProvider
{
    /**
     * Iterate all `HasNativeCommandsInterface` providers listed in the manifest
     * and register their commands into a fresh `CommandRegistry`.
     *
     * @param list<object>|null $providerInstances
     *   When non-null, use these already-instantiated providers instead of
     *   resolving from the manifest (useful in tests).
     */
    public static function buildRegistry(
        PackageManifest $manifest,
        ContainerInterface $container,
        ?array $providerInstances = null,
        ?LoggerInterface $logger = null,
    ): CommandRegistry {
        $logger ??= new NullLogger();
        $registry = new CommandRegistry();

        $providers = $providerInstances ?? self::resolveProviders($manifest, $container, $logger);

        foreach ($providers as $provider) {
            if (!$provider instanceof HasNativeCommandsInterface) {
                continue;
            }

            foreach ($provider->nativeCommands() as $command) {
                if (!$command instanceof CommandDefinition) {
                    $logger->warning(sprintf(
                        'Provider "%s" yielded a non-CommandDefinition value (%s); skipped.',
                        $provider::class,
                        get_debug_type($command),
                    ));
                    continue;
                }

                try {
                    $registry->register($command);
                } catch (\Waaseyaa\CLI\Exception\DuplicateCommandException $e) {
                    $logger->warning(sprintf(
                        'Duplicate command "%s" from provider "%s": %s',
                        $command->name,
                        $provider::class,
                        $e->getMessage(),
                    ));
                }
            }
        }

        return $registry;
    }

    /**
     * Build a fully-wired `CliKernel` from a `CommandRegistry`.
     *
     * Output streams default to STDOUT/STDERR if the container cannot supply them.
     */
    public static function buildKernel(
        CommandRegistry $registry,
        ContainerInterface $container,
        ?LoggerInterface $logger = null,
    ): CliKernel {
        $logger ??= new NullLogger();

        $stdout = self::resolveOrFallback(
            $container,
            \Waaseyaa\CLI\Io\CliOutput::class . '.stdout',
            static fn() => new StreamCliOutput(STDOUT),
        );

        $stderr = self::resolveOrFallback(
            $container,
            \Waaseyaa\CLI\Io\CliOutput::class . '.stderr',
            static fn() => new StreamCliOutput(STDERR),
        );

        $stdin = self::resolveOrFallback(
            $container,
            StdinSource::class,
            static fn() => new StreamStdinSource(STDIN),
        );

        return new CliKernel(
            registry: $registry,
            container: $container,
            help: new HelpRenderer(),
            stdout: $stdout,
            stderr: $stderr,
            stdin: $stdin,
            logger: $logger,
        );
    }

    /**
     * Resolve provider instances from the manifest via the container.
     *
     * @return list<object>
     */
    private static function resolveProviders(
        PackageManifest $manifest,
        ContainerInterface $container,
        LoggerInterface $logger,
    ): array {
        $instances = [];

        foreach ($manifest->nativeCommandProviders as $providerClass) {
            try {
                $instance = $container->get($providerClass);
                if (is_object($instance)) {
                    $instances[] = $instance;
                }
            } catch (\Throwable $e) {
                $logger->warning(sprintf(
                    'Could not resolve native command provider "%s": %s',
                    $providerClass,
                    $e->getMessage(),
                ));
            }
        }

        return $instances;
    }

    /**
     * @template T
     * @param \Closure(): T $fallback
     * @return T
     */
    private static function resolveOrFallback(
        ContainerInterface $container,
        string $id,
        \Closure $fallback,
    ): mixed {
        try {
            if ($container->has($id)) {
                return $container->get($id);
            }
        } catch (\Throwable) {
            // fall through to default
        }

        return $fallback();
    }
}

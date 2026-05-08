<?php

declare(strict_types=1);

namespace Waaseyaa\CLI;

use Psr\Container\ContainerInterface;
use Waaseyaa\CLI\Provider\CliKernelServiceProvider;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Discovery\PackageManifestCompiler;
use Waaseyaa\Foundation\Kernel\ConsoleKernel;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * Static entry point for the native CLI surface.
 *
 * Responsibilities:
 *   1. Load the PackageManifest (from cache or by compiling).
 *   2. Build the CommandRegistry via CliKernelServiceProvider (native commands).
 *   3. Build the CliKernel and run it.
 *   4. Call exit() — the ONLY place exit() is called in the CLI surface.
 *
 * The `run()` variant returns the exit code for testability (no exit).
 *
 * Full contract: kitty-specs/native-cli-kernel-01KR2NR7/contracts/cli-kernel.md
 */
final class CliApplication
{
    /**
     * Bootstrap and run the CLI application, then terminate the process.
     *
     * Called from `bin/waaseyaa` after the Composer autoloader has been loaded.
     * Calls exit() with the exit code returned by CliKernel::run().
     *
     * @param list<string>  $argv        Argv tokens without the script name
     *                                   (i.e. pass array_slice($_SERVER['argv'], 1)).
     * @param string        $projectRoot Absolute project root (contains composer.json
     *                                   and vendor/).
     * @param list<object>|null $providers        Pre-booted service provider instances.
     *                                            When null, an empty list is used.
     * @param ContainerInterface|null $container  DI container for handler resolution.
     *                                            When null, a NullContainer is used.
     * @param LoggerInterface|null $logger
     * @return never
     */
    public static function main(
        array $argv,
        string $projectRoot,
        ?array $providers = null,
        ?ContainerInterface $container = null,
        ?LoggerInterface $logger = null,
    ): never {
        $exitCode = self::run(
            argv: $argv,
            projectRoot: $projectRoot,
            providers: $providers,
            container: $container,
            logger: $logger,
        );
        exit($exitCode);
    }

    /**
     * Same as main() but returns the exit code instead of calling exit().
     *
     * Use this in integration tests so the test process is not terminated.
     *
     * @param list<string>  $argv
     * @param list<object>|null $providers
     */
    public static function run(
        array $argv,
        string $projectRoot,
        ?array $providers = null,
        ?ContainerInterface $container = null,
        ?LoggerInterface $logger = null,
    ): int {
        $logger ??= new NullLogger();

        // When no providers/container given, boot the ConsoleKernel to discover
        // all registered service providers and build a container for handler resolution.
        if ($providers === null && $container === null) {
            $kernelBoot = new ConsoleKernel($projectRoot, $logger);
            try {
                $kernelBoot->bootForCli();
                $providers = $kernelBoot->getProviders();
                $container = $kernelBoot->buildHandlerContainer();
            } catch (\Throwable $e) {
                fwrite(STDERR, sprintf("[Waaseyaa] Boot failed: %s\n", $e->getMessage()));
                $providers = [];
            }
        }

        $container ??= self::makeNullContainer();
        $providers ??= [];

        $manifest = self::loadManifest($projectRoot);

        $registry = CliKernelServiceProvider::buildRegistry(
            manifest: $manifest,
            container: $container,
            providerInstances: $providers,
            logger: $logger,
            projectRoot: $projectRoot,
        );

        $kernel = CliKernelServiceProvider::buildKernel(
            registry: $registry,
            container: $container,
            logger: $logger,
        );

        return $kernel->run($argv);
    }

    /**
     * Load the PackageManifest from the cached file, or compile it fresh.
     */
    private static function loadManifest(string $projectRoot): PackageManifest
    {
        $storagePath = $projectRoot . '/storage';
        $cached = $storagePath . '/framework/packages.php';

        if (file_exists($cached)) {
            try {
                $data = require $cached;
                if (is_array($data)) {
                    return PackageManifest::fromArray($data);
                }
            } catch (\Throwable) {
                // Cache corrupt — fall through to compile.
            }
        }

        $compiler = new PackageManifestCompiler(
            basePath: $projectRoot,
            storagePath: $storagePath,
        );

        return $compiler->compile();
    }

    /**
     * Create a minimal ContainerInterface that always throws NotFound.
     *
     * Used when no DI container is available (e.g. minimal bin bootstrap).
     * CliKernel falls back to closure-only handlers in this mode.
     */
    private static function makeNullContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new class ($id) extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {
                    public function __construct(string $id)
                    {
                        parent::__construct(sprintf('No binding for "%s" in NullContainer.', $id));
                    }
                };
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}

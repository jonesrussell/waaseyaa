<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel;

use Waaseyaa\Access\PermissionHandler;
use Waaseyaa\Cache\Backend\DatabaseBackend;
use Waaseyaa\Cache\CacheConfiguration;
use Waaseyaa\Cache\CacheFactory;
use Waaseyaa\CLI\CliCommandRegistry;
use Waaseyaa\CLI\CliKernel;
use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\Io\StreamCliOutput;
use Waaseyaa\CLI\Provider\ConfigCacheDbAuditServiceProvider;
use Waaseyaa\CLI\Provider\MiscBServiceProvider;
use Waaseyaa\CLI\WaaseyaaApplication;
use Waaseyaa\Config\ConfigManager;
use Waaseyaa\Config\Storage\FileStorage;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Foundation\Discovery\StaleManifestException;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasCommandsInterface;
use Waaseyaa\Routing\WaaseyaaRouter;

final class ConsoleKernel extends AbstractKernel
{
    public function handle(): int
    {
        if ($this->shouldUseMinimalConsole()) {
            return $this->runMinimalConsole();
        }

        try {
            $this->boot();
        } catch (StaleManifestException $e) {
            fwrite(STDERR, sprintf(
                "[Waaseyaa] %s\n",
                $e->getMessage(),
            ));
            return 1;
        } catch (\Throwable $e) {
            fwrite(STDERR, sprintf(
                "[Waaseyaa] Bootstrap failed: %s\n  in %s:%d\n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));
            return 1;
        }

        try {
            $configDir = $this->config['config_dir']
                ?? (getenv('WAASEYAA_CONFIG_DIR') ?: $this->projectRoot . '/config/sync');
            $activeDir = $this->projectRoot . '/config/active';

            if (!is_dir($activeDir) && !mkdir($activeDir, 0755, true) && !is_dir($activeDir)) {
                throw new \RuntimeException(sprintf('Unable to create config active directory: %s', $activeDir));
            }
            if (!is_dir($configDir) && !mkdir($configDir, 0755, true) && !is_dir($configDir)) {
                throw new \RuntimeException(sprintf('Unable to create config sync directory: %s', $configDir));
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, sprintf(
                "[Waaseyaa] Startup failed: %s\n  in %s:%d\n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));
            return 1;
        }

        $activeStorage = new FileStorage($activeDir);
        $syncStorage = new FileStorage($configDir);
        $configManager = new ConfigManager($activeStorage, $syncStorage, $this->dispatcher);

        // Escape hatch: components that still require raw PDO (cache, embeddings).
        // These will be migrated to DBAL Connection in a future PR.
        assert($this->database instanceof \Waaseyaa\Database\DBALDatabase);
        $pdo = $this->database->getConnection()->getNativeConnection();
        assert($pdo instanceof \PDO);

        $cacheConfig = new CacheConfiguration();
        $cacheConfig->setFactoryForBin('render', fn(): DatabaseBackend => new DatabaseBackend(
            $pdo,
            'cache_render',
        ));
        $cacheFactory = new CacheFactory($cacheConfig);
        $router = new WaaseyaaRouter();
        $permissionHandler = new PermissionHandler();
        $typeIdNormalizer = new EntityTypeIdNormalizer($this->entityTypeManager);

        $app = new WaaseyaaApplication();
        $app->setAutoExit(false);
        $commandRegistry = new CliCommandRegistry();

        $app->registerCommands($commandRegistry->coreCommands(
            projectRoot: $this->projectRoot,
            config: $this->config,
            manifest: $this->manifest,
            dispatcher: $this->dispatcher,
            entityTypeManager: $this->entityTypeManager,
            lifecycleManager: $this->lifecycleManager,
            entityAuditLogger: $this->entityAuditLogger,
            database: $this->database,
            configManager: $configManager,
            cacheFactory: $cacheFactory,
            router: $router,
            permissionHandler: $permissionHandler,
            typeIdNormalizer: $typeIdNormalizer,
            pdo: $pdo,
        ));

        foreach ($this->providers as $provider) {
            if (!$provider instanceof HasCommandsInterface) {
                continue;
            }
            $pluginCommands = $provider->commands($this->entityTypeManager, $this->database, $this->dispatcher);
            if ($pluginCommands !== []) {
                $app->registerCommands($pluginCommands);
            }
        }

        return $app->run();
    }

    /**
     * Return all booted Symfony Console commands for the dual-boot bridge.
     *
     * Called by CliApplication after bootForCli() so the native CliKernel can
     * expose all legacy Symfony commands through LegacySymfonyCommandRegistrar.
     *
     * @internal Used by CliApplication. Deleted in WP23 (native-cli-kernel-01KR2NR7).
     * @return list<\Symfony\Component\Console\Command\Command>
     */
    public function buildBootedSymfonyCommands(): array
    {
        $envConfigDir = getenv('WAASEYAA_CONFIG_DIR');
        $configDir = $this->config['config_dir']
            ?? ($envConfigDir !== false && $envConfigDir !== '' ? $envConfigDir : $this->projectRoot . '/config/sync');
        $activeDir = $this->projectRoot . '/config/active';

        if (!is_dir($activeDir)) {
            @mkdir($activeDir, 0755, true);
        }
        if (!is_dir($configDir)) {
            @mkdir($configDir, 0755, true);
        }

        $activeStorage = new \Waaseyaa\Config\Storage\FileStorage($activeDir);
        $syncStorage   = new \Waaseyaa\Config\Storage\FileStorage($configDir);
        $configManager = new \Waaseyaa\Config\ConfigManager($activeStorage, $syncStorage, $this->dispatcher);

        assert($this->database instanceof \Waaseyaa\Database\DBALDatabase);
        $pdo = $this->database->getConnection()->getNativeConnection();
        assert($pdo instanceof \PDO);

        $cacheConfig = new \Waaseyaa\Cache\CacheConfiguration();
        $cacheFactory = new \Waaseyaa\Cache\CacheFactory($cacheConfig);
        $router = new \Waaseyaa\Routing\WaaseyaaRouter();
        $permissionHandler = new \Waaseyaa\Access\PermissionHandler();
        $typeIdNormalizer = new \Waaseyaa\Entity\EntityTypeIdNormalizer($this->entityTypeManager);

        $commandRegistry = new \Waaseyaa\CLI\CliCommandRegistry();
        $commands = $commandRegistry->coreCommands(
            projectRoot: $this->projectRoot,
            config: $this->config,
            manifest: $this->manifest,
            dispatcher: $this->dispatcher,
            entityTypeManager: $this->entityTypeManager,
            lifecycleManager: $this->lifecycleManager,
            entityAuditLogger: $this->entityAuditLogger,
            database: $this->database,
            configManager: $configManager,
            cacheFactory: $cacheFactory,
            router: $router,
            permissionHandler: $permissionHandler,
            typeIdNormalizer: $typeIdNormalizer,
            pdo: $pdo,
        );

        foreach ($this->providers as $provider) {
            if (!$provider instanceof HasCommandsInterface) {
                continue;
            }
            $pluginCmds = $provider->commands($this->entityTypeManager, $this->database, $this->dispatcher);
            if ($pluginCmds !== []) {
                $commands = array_merge($commands, $pluginCmds);
            }
        }

        return $commands;
    }

    private function shouldUseMinimalConsole(): bool
    {
        $name = $this->requestedCommandName();

        return $name === 'optimize:manifest' || $name === 'waaseyaa:version' || $name === 'db:init';
    }

    private function requestedCommandName(): ?string
    {
        $argv = $_SERVER['argv'] ?? [];

        foreach (array_slice($argv, 1) as $arg) {
            if (!is_string($arg)) {
                continue;
            }

            if ($arg === '' || str_starts_with($arg, '-')) {
                continue;
            }

            return $arg;
        }

        return null;
    }

    private function runMinimalConsole(): int
    {
        $app = new WaaseyaaApplication();
        $app->setAutoExit(false);
        $requested = $this->requestedCommandName();
        if ($requested === 'waaseyaa:version') {
            // waaseyaa:version is now a native command served via MiscBServiceProvider.
            $registry = new CommandRegistry();
            $provider = new MiscBServiceProvider();
            $provider->setKernelContext($this->projectRoot, [], []);
            foreach ($provider->nativeCommands() as $cmd) {
                if ($cmd->name === 'waaseyaa:version') {
                    $registry->register($cmd);
                    break;
                }
            }
            $container = new class implements \Psr\Container\ContainerInterface {
                public function get(string $id): mixed
                {
                    throw new \RuntimeException('Container not available in minimal console.');
                }
                public function has(string $id): bool
                {
                    return false;
                }
            };
            $stdout = new StreamCliOutput(STDOUT);
            $stderr = new StreamCliOutput(STDERR);
            $kernel = new CliKernel(
                registry: $registry,
                container: $container,
                help: new HelpRenderer(),
                stdout: $stdout,
                stderr: $stderr,
                stdin: new EmptyStdinSource(),
            );
            return $kernel->run(array_slice($_SERVER['argv'] ?? [], 1));
        } elseif ($requested === 'db:init') {
            // db:init is now a native command served via ConfigCacheDbAuditServiceProvider.
            // Build a minimal CliKernel with only the db:init command registered.
            $registry = new CommandRegistry();
            $provider = new ConfigCacheDbAuditServiceProvider();
            $provider->setKernelContext($this->projectRoot, [], []);
            foreach ($provider->nativeCommands() as $cmd) {
                if ($cmd->name === 'db:init') {
                    $registry->register($cmd);
                    break;
                }
            }
            $container = new class implements \Psr\Container\ContainerInterface {
                public function get(string $id): mixed
                {
                    throw new \RuntimeException('Container not available in minimal console.');
                }
                public function has(string $id): bool
                {
                    return false;
                }
            };
            $stdout = new StreamCliOutput(STDOUT);
            $stderr = new StreamCliOutput(STDERR);
            $kernel = new CliKernel(
                registry: $registry,
                container: $container,
                help: new HelpRenderer(),
                stdout: $stdout,
                stderr: $stderr,
                stdin: new EmptyStdinSource(),
            );
            return $kernel->run(array_slice($_SERVER['argv'] ?? [], 1));
        } else {
            // optimize:manifest is now handled by OptimizeServiceProvider via the native CliKernel.
            // The minimal console path is not reached for native commands.
        }

        return $app->run();
    }
}

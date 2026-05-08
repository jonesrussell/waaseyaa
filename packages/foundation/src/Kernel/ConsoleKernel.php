<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel;

use Waaseyaa\Access\PermissionHandler;
use Waaseyaa\AI\Vector\EmbeddingProviderFactory;
use Waaseyaa\AI\Vector\SemanticIndexWarmer;
use Waaseyaa\AI\Vector\SqliteEmbeddingStorage;
use Waaseyaa\Cache\Backend\DatabaseBackend;
use Waaseyaa\Cache\CacheConfiguration;
use Waaseyaa\Cache\CacheFactory;
use Waaseyaa\CLI\CliCommandRegistry;
use Waaseyaa\CLI\Command\DbInitCommand;
use Waaseyaa\CLI\Command\Optimize\OptimizeManifestCommand;
use Waaseyaa\CLI\Command\WaaseyaaVersionCommand;
use Waaseyaa\CLI\WaaseyaaApplication;
use Waaseyaa\Config\ConfigManager;
use Waaseyaa\Config\Storage\FileStorage;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Foundation\Diagnostic\HealthChecker;
use Waaseyaa\Foundation\Discovery\PackageManifestCompiler;
use Waaseyaa\Foundation\Discovery\StaleManifestException;
use Waaseyaa\Foundation\Schema\DefaultsSchemaRegistry;
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
        $manifestCompiler = new PackageManifestCompiler(
            basePath: $this->projectRoot,
            storagePath: $this->projectRoot . '/storage',
        );
        $semanticWarmer = null;
        if (class_exists(SqliteEmbeddingStorage::class)) {
            $embeddingStorage = new SqliteEmbeddingStorage($pdo);
            $embeddingProvider = EmbeddingProviderFactory::fromConfig($this->config);
            $semanticWarmer = new SemanticIndexWarmer(
                entityTypeManager: $this->entityTypeManager,
                embeddingStorage: $embeddingStorage,
                embeddingProvider: $embeddingProvider,
            );
        }
        $schemaRegistry = new DefaultsSchemaRegistry($this->projectRoot . '/defaults');
        $healthChecker = new HealthChecker(
            bootReport: $this->getBootReport(),
            database: $this->database,
            entityTypeManager: $this->entityTypeManager,
            projectRoot: $this->projectRoot,
            fieldRegistry: $this->fieldRegistry,
        );

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
            manifestCompiler: $manifestCompiler,
            schemaRegistry: $schemaRegistry,
            healthChecker: $healthChecker,
            typeIdNormalizer: $typeIdNormalizer,
            semanticWarmer: $semanticWarmer,
            pdo: $pdo,
        ));

        $migrationsProvider = fn() => $this->migrationLoader->loadAll();
        $v2MigrationsProvider = fn(): array => $this->migrationLoader->loadAllV2();
        $compiler = \Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler::forVersion('3.40.0');
        $app->registerCommands($commandRegistry->migrationCommands(
            $this->migrator,
            $migrationsProvider,
            $v2MigrationsProvider,
            $this->migrationRepository,
            $compiler,
            ! $this->isDevelopmentMode(),
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
        $manifestCompiler = new \Waaseyaa\Foundation\Discovery\PackageManifestCompiler(
            basePath: $this->projectRoot,
            storagePath: $this->projectRoot . '/storage',
        );
        $schemaRegistry = new \Waaseyaa\Foundation\Schema\DefaultsSchemaRegistry($this->projectRoot . '/defaults');
        $healthChecker = new \Waaseyaa\Foundation\Diagnostic\HealthChecker(
            bootReport: $this->getBootReport(),
            database: $this->database,
            entityTypeManager: $this->entityTypeManager,
            projectRoot: $this->projectRoot,
            fieldRegistry: $this->fieldRegistry,
        );
        $typeIdNormalizer = new \Waaseyaa\Entity\EntityTypeIdNormalizer($this->entityTypeManager);

        $semanticWarmer = null;
        if (class_exists(\Waaseyaa\AI\Vector\SqliteEmbeddingStorage::class)) {
            $embeddingStorage = new \Waaseyaa\AI\Vector\SqliteEmbeddingStorage($pdo);
            $embeddingProvider = \Waaseyaa\AI\Vector\EmbeddingProviderFactory::fromConfig($this->config);
            $semanticWarmer = new \Waaseyaa\AI\Vector\SemanticIndexWarmer(
                entityTypeManager: $this->entityTypeManager,
                embeddingStorage: $embeddingStorage,
                embeddingProvider: $embeddingProvider,
            );
        }

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
            manifestCompiler: $manifestCompiler,
            schemaRegistry: $schemaRegistry,
            healthChecker: $healthChecker,
            typeIdNormalizer: $typeIdNormalizer,
            semanticWarmer: $semanticWarmer,
            pdo: $pdo,
        );

        $migrationsProvider   = fn() => $this->migrationLoader->loadAll();
        $v2MigrationsProvider = fn(): array => $this->migrationLoader->loadAllV2();
        $compiler = \Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler::forVersion('3.40.0');
        $commands = array_merge($commands, $commandRegistry->migrationCommands(
            $this->migrator,
            $migrationsProvider,
            $v2MigrationsProvider,
            $this->migrationRepository,
            $compiler,
            !$this->isDevelopmentMode(),
        ));

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
            $app->registerCommands([
                new WaaseyaaVersionCommand($this->projectRoot),
            ]);
        } elseif ($requested === 'db:init') {
            $app->registerCommands([
                new DbInitCommand($this->projectRoot),
            ]);
        } else {
            $app->registerCommands([
                new OptimizeManifestCommand(new PackageManifestCompiler(
                    basePath: $this->projectRoot,
                    storagePath: $this->projectRoot . '/storage',
                )),
            ]);
        }

        return $app->run();
    }
}

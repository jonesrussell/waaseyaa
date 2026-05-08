<?php

declare(strict_types=1);

namespace Waaseyaa\CLI;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Access\PermissionHandler;
use Waaseyaa\AI\Vector\SemanticIndexWarmer;
use Waaseyaa\Cache\CacheFactory;
use Waaseyaa\CLI\Command\AboutCommand;
use Waaseyaa\CLI\Command\AdminBuildCommand;
use Waaseyaa\CLI\Command\AdminDevCommand;
use Waaseyaa\CLI\Command\AuditLogCommand;
use Waaseyaa\CLI\Command\BundleScaffoldCommand;
use Waaseyaa\CLI\Command\CacheClearCommand;
use Waaseyaa\CLI\Command\ConfigExportCommand;
use Waaseyaa\CLI\Command\ConfigImportCommand;
use Waaseyaa\CLI\Command\DbInitCommand;
use Waaseyaa\CLI\Command\DebugContextCommand;
use Waaseyaa\CLI\Command\EntityCreateCommand;
use Waaseyaa\CLI\Command\EntityListCommand;
use Waaseyaa\CLI\Command\EntityTypeListCommand;
use Waaseyaa\CLI\Command\EventListCommand;
use Waaseyaa\CLI\Command\ExtensionScaffoldCommand;
use Waaseyaa\CLI\Command\FixtureGenerateCommand;
use Waaseyaa\CLI\Command\FixturePackRefreshCommand;
use Waaseyaa\CLI\Command\FixtureScaffoldCommand;
use Waaseyaa\CLI\Command\IngestDashboardCommand;
use Waaseyaa\CLI\Command\IngestRunCommand;
use Waaseyaa\CLI\Command\InstallCommand;
use Waaseyaa\CLI\Command\Make\MakeProviderCommand;
use Waaseyaa\CLI\Command\Make\MakePublicCommand;
use Waaseyaa\CLI\Command\Make\MakeTestCommand;
use Waaseyaa\CLI\Command\MakeEntityTypeCommand;
use Waaseyaa\CLI\Command\MakePluginCommand;
use Waaseyaa\CLI\Command\Optimize\OptimizeClearCommand;
use Waaseyaa\CLI\Command\Optimize\OptimizeCommand;
use Waaseyaa\CLI\Command\Optimize\OptimizeConfigCommand;
use Waaseyaa\CLI\Command\Optimize\OptimizeManifestCommand;
use Waaseyaa\CLI\Command\Perf\PerformanceBaselineCommand;
use Waaseyaa\CLI\Command\Perf\PerformanceCompareCommand;
use Waaseyaa\CLI\Command\PermissionListCommand;
use Waaseyaa\CLI\Command\RelationshipTypeScaffoldCommand;
use Waaseyaa\CLI\Command\RouteListCommand;
use Waaseyaa\CLI\Command\ScaffoldAuthCommand;
use Waaseyaa\CLI\Command\SemanticRefreshCommand;
use Waaseyaa\CLI\Command\SemanticWarmCommand;
use Waaseyaa\CLI\Command\ServeCommand;
use Waaseyaa\CLI\Command\SyncRulesCommand;
use Waaseyaa\CLI\Command\Telescope\TelescopeClearCommand;
use Waaseyaa\CLI\Command\Telescope\TelescopeListCommand;
use Waaseyaa\CLI\Command\Telescope\TelescopePruneCommand;
use Waaseyaa\CLI\Command\TypeDisableCommand;
use Waaseyaa\CLI\Command\TypeEnableCommand;
use Waaseyaa\CLI\Command\UserCreateCommand;
use Waaseyaa\CLI\Command\UserRoleCommand;
use Waaseyaa\CLI\Command\WaaseyaaVersionCommand;
use Waaseyaa\CLI\Command\WorkflowScaffoldCommand;
use Waaseyaa\Config\Cache\ConfigCacheCompiler;
use Waaseyaa\Config\ConfigManager;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\Audit\EntityAuditLogger;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Entity\EntityTypeLifecycleManager;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Discovery\PackageManifestCompiler;
use Waaseyaa\Routing\WaaseyaaRouter;

final class CliCommandRegistry
{
    /**
     * @param array<string, mixed> $config
     * @return list<\Symfony\Component\Console\Command\Command>
     */
    public function coreCommands(
        string $projectRoot,
        array $config,
        PackageManifest $manifest,
        EventDispatcherInterface $dispatcher,
        EntityTypeManager $entityTypeManager,
        EntityTypeLifecycleManager $lifecycleManager,
        EntityAuditLogger $entityAuditLogger,
        DatabaseInterface $database,
        ConfigManager $configManager,
        CacheFactory $cacheFactory,
        WaaseyaaRouter $router,
        PermissionHandler $permissionHandler,
        PackageManifestCompiler $manifestCompiler,
        EntityTypeIdNormalizer $typeIdNormalizer,
        ?SemanticIndexWarmer $semanticWarmer,
        \PDO $pdo,
    ): array {
        return [
            new DbInitCommand($projectRoot),
            new InstallCommand($entityTypeManager, $configManager),
            new CacheClearCommand($cacheFactory),
            new ConfigExportCommand($configManager),
            new ConfigImportCommand($configManager),
            new DebugContextCommand(),
            new EntityCreateCommand($entityTypeManager),
            new EntityListCommand($entityTypeManager),
            new UserCreateCommand($entityTypeManager),
            new UserRoleCommand($entityTypeManager),
            new MakePluginCommand(),
            new MakeEntityTypeCommand(),
            new MakeProviderCommand(),
            new MakePublicCommand($projectRoot),
            new MakeTestCommand(),
            new ScaffoldAuthCommand($projectRoot),
            new ServeCommand($projectRoot),
            new AdminDevCommand($projectRoot),
            new AdminBuildCommand($projectRoot),
            new AboutCommand(info: [
                'name' => 'Waaseyaa',
                'version' => (static function (): string {
                    $pkg = \Composer\InstalledVersions::getRootPackage();
                    return $pkg['pretty_version'];
                })(),
                'php' => PHP_VERSION,
                'environment' => getenv('APP_ENV') ?: 'production',
            ]),
            new EntityTypeListCommand($entityTypeManager),
            new TypeDisableCommand($entityTypeManager, $lifecycleManager, $typeIdNormalizer),
            new TypeEnableCommand($entityTypeManager, $lifecycleManager, $typeIdNormalizer),
            new AuditLogCommand($lifecycleManager, $entityAuditLogger),
            new EventListCommand($dispatcher),
            new RouteListCommand($router),
            new PermissionListCommand($permissionHandler),
            ...($semanticWarmer !== null ? [
                new SemanticWarmCommand($semanticWarmer),
                new SemanticRefreshCommand($semanticWarmer),
            ] : []),
            new FixtureScaffoldCommand(),
            new FixtureGenerateCommand(),
            new FixturePackRefreshCommand(),
            new IngestDashboardCommand(),
            new IngestRunCommand(),
            new BundleScaffoldCommand(),
            new RelationshipTypeScaffoldCommand(),
            new WorkflowScaffoldCommand(),
            new ExtensionScaffoldCommand(),
            new OptimizeCommand(),
            new OptimizeManifestCommand($manifestCompiler),
            new OptimizeConfigCommand(new ConfigCacheCompiler(
                $configManager->getActiveStorage(),
                $projectRoot . '/storage/framework/config.php',
            )),
            new OptimizeClearCommand($projectRoot . '/storage'),
            new PerformanceBaselineCommand(),
            new PerformanceCompareCommand(),
            new TelescopeClearCommand(),
            new TelescopeListCommand(),
            new TelescopePruneCommand(),
            new SyncRulesCommand(
                $projectRoot . '/vendor/waaseyaa/foundation/.claude/rules',
                $projectRoot . '/.claude/rules',
            ),
            new WaaseyaaVersionCommand($projectRoot),
        ];
    }

}

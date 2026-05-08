<?php

declare(strict_types=1);

namespace Waaseyaa\CLI;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Access\PermissionHandler;
use Waaseyaa\Cache\CacheFactory;
use Waaseyaa\CLI\Command\InstallCommand;
use Waaseyaa\CLI\Command\RouteListCommand;
use Waaseyaa\CLI\Command\ServeCommand;
use Waaseyaa\CLI\Command\SyncRulesCommand;
use Waaseyaa\CLI\Command\WaaseyaaVersionCommand;
use Waaseyaa\Config\ConfigManager;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\Audit\EntityAuditLogger;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Entity\EntityTypeLifecycleManager;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Discovery\PackageManifest;
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
        EntityTypeIdNormalizer $typeIdNormalizer,
        \PDO $pdo,
    ): array {
        return [
            new InstallCommand($entityTypeManager, $configManager),
            new ServeCommand($projectRoot),
            new RouteListCommand($router),
            new SyncRulesCommand(
                $projectRoot . '/vendor/waaseyaa/foundation/.claude/rules',
                $projectRoot . '/.claude/rules',
            ),
            new WaaseyaaVersionCommand($projectRoot),
        ];
    }

}

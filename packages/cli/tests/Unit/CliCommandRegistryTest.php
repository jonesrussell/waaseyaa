<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Access\PermissionHandler;
use Waaseyaa\CLI\CliCommandRegistry;
use Waaseyaa\Cache\CacheConfiguration;
use Waaseyaa\Cache\CacheFactory;
use Waaseyaa\Config\ConfigManager;
use Waaseyaa\Config\Storage\FileStorage;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Entity\EntityTypeLifecycleManager;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Audit\EntityAuditLogger;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Routing\WaaseyaaRouter;

#[CoversClass(CliCommandRegistry::class)]
final class CliCommandRegistryTest extends TestCase
{
    #[Test]
    public function core_commands_are_owned_by_the_cli_registry(): void
    {
        $projectRoot = sys_get_temp_dir() . '/waaseyaa_cli_registry_' . uniqid();
        mkdir($projectRoot . '/config/active', 0755, true);
        mkdir($projectRoot . '/config/sync', 0755, true);
        mkdir($projectRoot . '/defaults', 0755, true);
        mkdir($projectRoot . '/storage', 0755, true);

        try {
            $dispatcher = new EventDispatcher();
            $entityTypeManager = new EntityTypeManager($dispatcher);
            $entityTypeManager->registerEntityType(new EntityType(
                id: 'node',
                label: 'Node',
                class: \stdClass::class,
                keys: ['id' => 'id'],
            ));

            $database = DBALDatabase::createSqlite();
            $activeStorage = new FileStorage($projectRoot . '/config/active');
            $syncStorage = new FileStorage($projectRoot . '/config/sync');
            $configManager = new ConfigManager($activeStorage, $syncStorage, $dispatcher);

            assert($database instanceof DBALDatabase);
            $pdo = $database->getConnection()->getNativeConnection();
            assert($pdo instanceof \PDO);

            $cacheFactory = new CacheFactory(new CacheConfiguration());
            $router = new WaaseyaaRouter();
            $registry = new CliCommandRegistry();
            $commands = $registry->coreCommands(
                projectRoot: $projectRoot,
                config: [],
                manifest: new PackageManifest(),
                dispatcher: $dispatcher,
                entityTypeManager: $entityTypeManager,
                lifecycleManager: new EntityTypeLifecycleManager($projectRoot),
                entityAuditLogger: new EntityAuditLogger($projectRoot),
                database: $database,
                configManager: $configManager,
                cacheFactory: $cacheFactory,
                router: $router,
                permissionHandler: new PermissionHandler(),
                typeIdNormalizer: new EntityTypeIdNormalizer($entityTypeManager),
                pdo: $pdo,
            );

            // coreCommands() now returns [] — all commands ported to native CLI providers.
            $this->assertSame([], $commands);
            $names = [];

            // Ported to MiscBServiceProvider (WP21):
            $this->assertNotContains('route:list', $names);
            $this->assertNotContains('waaseyaa:version', $names);
            $this->assertNotContains('install', $names);
            $this->assertNotContains('serve', $names);
            $this->assertNotContains('sync-rules', $names);
            // Ported to MiscAServiceProvider (WP20):
            $this->assertNotContains('about', $names);
            $this->assertNotContains('admin:dev', $names);
            $this->assertNotContains('admin:build', $names);
            $this->assertNotContains('debug:context', $names);
            $this->assertNotContains('event:list', $names);
            // scaffold:auth was ported to native CLI in WP19.
            $this->assertNotContains('scaffold:auth', $names);
        } finally {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($projectRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($projectRoot);
        }
    }
}

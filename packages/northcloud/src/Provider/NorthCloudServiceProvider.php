<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Provider;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\NorthCloud\Client\NorthCloudCache;
use Waaseyaa\NorthCloud\Client\NorthCloudClient;
use Waaseyaa\NorthCloud\Command\NcSyncCommand;
use Waaseyaa\NorthCloud\Search\NorthCloudSearchProvider;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;
use Waaseyaa\NorthCloud\Sync\NcSyncService;

/**
 * Wires the NorthCloud package into a Waaseyaa application.
 *
 * Registers the HTTP client, mapper registry, sync service, search provider,
 * and console command. Concrete NcHitToEntityMapperInterface implementations
 * are registered by the application (typically in its own service provider).
 *
 * Config is read from the `northcloud` key. See config/northcloud.php for
 * shipped defaults.
 */
final class NorthCloudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(MapperRegistry::class, fn(): MapperRegistry => new MapperRegistry());

        $this->singleton(NorthCloudClient::class, function (): NorthCloudClient {
            $config = $this->northcloudConfig();

            $cache = null;
            if ((bool) ($config['cache']['enabled'] ?? false)) {
                try {
                    $cache = $this->resolve(NorthCloudCache::class);
                } catch (\Throwable) {
                    // Cache optional — proceed without it if resolution fails.
                }
            }

            return new NorthCloudClient(
                baseUrl: (string) ($config['base_url'] ?? ''),
                timeout: (int) ($config['timeout'] ?? 5),
                cache: $cache,
                apiToken: (string) ($config['api_token'] ?? ''),
            );
        });

        $this->singleton(NcSyncService::class, function (): NcSyncService {
            $config = $this->northcloudConfig();

            return new NcSyncService(
                client: $this->resolve(NorthCloudClient::class),
                entityTypeManager: $this->resolve(EntityTypeManager::class),
                mappers: $this->resolve(MapperRegistry::class),
                topics: (array) ($config['sync']['topics'] ?? ['indigenous']),
                minQuality: (int) ($config['sync']['min_quality'] ?? 60),
            );
        });

        $this->singleton(NorthCloudSearchProvider::class, function (): NorthCloudSearchProvider {
            $config = $this->northcloudConfig();

            return new NorthCloudSearchProvider(
                baseUrl: (string) ($config['base_url'] ?? ''),
                timeout: (int) ($config['timeout'] ?? 5),
                cacheTtl: (int) ($config['search']['cache_ttl'] ?? 300),
            );
        });

        $this->singleton(NcSyncCommand::class, function (): NcSyncCommand {
            $config = $this->northcloudConfig();

            return new NcSyncCommand(
                syncService: $this->resolve(NcSyncService::class),
                statusPath: $config['sync']['status_path'] ?? null,
            );
        });
    }

    /**
     * Expose console commands to the foundation so the host CLI surfaces
     * `northcloud:sync`. Without this hook the command is registered in the
     * container but never reaches the CLI kernel.
     *
     * Signature mirrors {@see ServiceProvider::commands()} — the foundation
     * auto-injects the arguments even though this provider doesn't use them.
     *
     * @return array<int, object>
     */
    public function commands(
        EntityTypeManager $entityTypeManager,
        DatabaseInterface $database,
        EventDispatcherInterface $dispatcher,
    ): array {
        return [
            $this->resolve(NcSyncCommand::class),
        ];
    }

    /** @return array<string, mixed> */
    private function northcloudConfig(): array
    {
        $section = $this->config['northcloud'] ?? [];
        return is_array($section) ? $section : [];
    }
}

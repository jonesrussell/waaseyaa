<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Provider;

use Symfony\Component\Console\Command\Command;
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
                allowInsecure: (bool) ($config['allow_insecure'] ?? false),
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
                client: $this->resolve(NorthCloudClient::class),
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
     * @return list<Command>
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

    /**
     * Read the `northcloud` config section, falling back to env vars when the
     * host app hasn't merged the package config. This lets consumers drop the
     * package in and set `NORTHCLOUD_BASE_URL` / `NORTHCLOUD_API_TOKEN` in
     * `.env` without having to publish the config file first.
     *
     * The provider is the single authority for reading NORTHCLOUD_* env vars;
     * the shipped config file (config/northcloud.php) uses plain string
     * defaults so there's no eager-read-vs-lazy-read drift.
     *
     * Env vars win over config defaults when set; empty config values fall
     * back to env vars which fall back to package defaults.
     *
     * @return array<string, mixed>
     */
    private function northcloudConfig(): array
    {
        $section = $this->config['northcloud'] ?? [];
        $section = is_array($section) ? $section : [];

        $envBaseUrl = getenv('NORTHCLOUD_BASE_URL');
        if ($envBaseUrl !== false && $envBaseUrl !== '') {
            $section['base_url'] = $envBaseUrl;
        } elseif (!isset($section['base_url']) || $section['base_url'] === '') {
            $section['base_url'] = 'https://api.northcloud.one';
        }

        $envToken = getenv('NORTHCLOUD_API_TOKEN');
        if ($envToken !== false && $envToken !== '') {
            $section['api_token'] = $envToken;
        } elseif (!isset($section['api_token'])) {
            $section['api_token'] = '';
        }

        $envAllowInsecure = getenv('NORTHCLOUD_ALLOW_INSECURE');
        if ($envAllowInsecure !== false && $envAllowInsecure !== '') {
            $section['allow_insecure'] = filter_var($envAllowInsecure, FILTER_VALIDATE_BOOLEAN);
        } elseif (!isset($section['allow_insecure'])) {
            // Auto-permit http only for obvious loopback dev targets; production URLs stay strict.
            $baseUrl = (string) ($section['base_url'] ?? '');
            $section['allow_insecure'] = str_starts_with($baseUrl, 'http://localhost')
                || str_starts_with($baseUrl, 'http://127.0.0.1');
        }

        return $section;
    }
}

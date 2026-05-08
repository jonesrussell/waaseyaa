<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\NcSyncHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\NorthCloud\Sync\NcSyncService;

final class NorthCloudServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'northcloud:sync',
            description: 'Pull content from the NorthCloud Search API and persist entities via registered mappers',
            options: [
                new OptionDefinition(
                    name: 'limit',
                    shortcut: 'l',
                    mode: OptionMode::Required,
                    description: 'Maximum hits to fetch',
                    default: '20',
                ),
                new OptionDefinition(
                    name: 'since',
                    shortcut: 's',
                    mode: OptionMode::Required,
                    description: 'Fetch content from this date (YYYY-MM-DD)',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Report what would be created without persisting',
                ),
                new OptionDefinition(
                    name: 'explain',
                    mode: OptionMode::None,
                    description: 'Show skip reason breakdown and sampled hit diagnostics',
                ),
                new OptionDefinition(
                    name: 'sample',
                    mode: OptionMode::Required,
                    description: 'Capture up to N created/skipped samples in output',
                    default: '10',
                ),
                new OptionDefinition(
                    name: 'report-json',
                    mode: OptionMode::Required,
                    description: 'Write sync report JSON to this path',
                ),
            ],
            handler: function (\Waaseyaa\CLI\CliIO $io): int {
                $northcloudConfig = $this->config['northcloud'] ?? [];
                $northcloudConfig = is_array($northcloudConfig) ? $northcloudConfig : [];
                $statusPath = $northcloudConfig['sync']['status_path'] ?? null;

                $handler = new NcSyncHandler(
                    syncService: $this->resolve(NcSyncService::class),
                    statusPath: is_string($statusPath) ? $statusPath : null,
                );

                return $handler->execute($io);
            },
        );
    }
}

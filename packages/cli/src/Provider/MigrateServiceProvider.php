<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\MigrateDefaultsHandler;
use Waaseyaa\CLI\Handler\MigrateHandler;
use Waaseyaa\CLI\Handler\MigrateRollbackHandler;
use Waaseyaa\CLI\Handler\MigrateStatusHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class MigrateServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'migrate',
            description: 'Run pending database migrations (use --dry-run to preview, --verify to audit)',
            options: [
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Preview pending migrations without applying any SQL or writing to the ledger.',
                ),
                new OptionDefinition(
                    name: 'verify',
                    mode: OptionMode::None,
                    description: 'Compare ledger checksums against the live source. Read-only.',
                ),
                new OptionDefinition(
                    name: 'json',
                    mode: OptionMode::None,
                    description: 'Emit machine-readable JSON instead of human-readable text.',
                ),
            ],
            handler: [MigrateHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'migrate:rollback',
            description: 'Roll back the last batch of migrations',
            handler: [MigrateRollbackHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'migrate:status',
            description: 'Show the status of each migration',
            handler: [MigrateStatusHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'migrate:defaults',
            description: 'Migrate default content type enablement for tenants',
            options: [
                new OptionDefinition(
                    name: 'tenant',
                    mode: OptionMode::Array_,
                    description: 'Tenant IDs to migrate (repeatable)',
                ),
                new OptionDefinition(
                    name: 'enable',
                    mode: OptionMode::Required,
                    description: 'Type ID to enable for all tenants (e.g. note)',
                    default: '',
                ),
                new OptionDefinition(
                    name: 'actor',
                    mode: OptionMode::Required,
                    description: 'Actor ID for audit log entries',
                    default: 'cli',
                ),
                new OptionDefinition(
                    name: 'yes',
                    shortcut: 'y',
                    mode: OptionMode::None,
                    description: 'Skip confirmation prompts',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Report actions without making changes',
                ),
                new OptionDefinition(
                    name: 'rollback',
                    mode: OptionMode::None,
                    description: 'Rollback previous migrate:defaults actions',
                ),
            ],
            handler: [MigrateDefaultsHandler::class, 'execute'],
        );
    }
}

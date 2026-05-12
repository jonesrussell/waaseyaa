<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\MakeStorageMigrationHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Registers the `make:storage-migration` command with the CLI kernel.
 *
 * @api
 */
final class MakeStorageMigrationServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    /**
     * @api
     */
    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'make:storage-migration',
            description: 'Generate a sql-column storage migration for an entity type',
            arguments: [
                new ArgumentDefinition(
                    name: 'entity_type_id',
                    mode: ArgumentMode::Required,
                    description: 'The entity type machine name (e.g. "node", "user")',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'target',
                    mode: OptionMode::Required,
                    description: 'Target backend id (default: sql-column)',
                    default: 'sql-column',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Print the migration to stdout without writing a file',
                ),
                new OptionDefinition(
                    name: 'force',
                    mode: OptionMode::None,
                    description: 'Overwrite an existing migration file for this entity type',
                ),
            ],
            handler: [MakeStorageMigrationHandler::class, 'execute'],
        );
    }
}

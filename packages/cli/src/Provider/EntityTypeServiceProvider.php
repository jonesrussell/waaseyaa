<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\EntityCreateHandler;
use Waaseyaa\CLI\Handler\EntityListHandler;
use Waaseyaa\CLI\Handler\EntityTypeListHandler;
use Waaseyaa\CLI\Handler\TypeDisableHandler;
use Waaseyaa\CLI\Handler\TypeEnableHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class EntityTypeServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'entity:create',
            description: 'Create a new entity of a given type',
            arguments: [
                new ArgumentDefinition(
                    name: 'entity_type',
                    mode: ArgumentMode::Required,
                    description: 'The entity type ID',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'values',
                    mode: OptionMode::Required,
                    description: 'JSON string of entity values',
                    default: '{}',
                ),
            ],
            handler: [EntityCreateHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'entity:list',
            description: 'List entities of a given type',
            arguments: [
                new ArgumentDefinition(
                    name: 'entity_type',
                    mode: ArgumentMode::Required,
                    description: 'The entity type ID',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'limit',
                    shortcut: 'l',
                    mode: OptionMode::Required,
                    description: 'Maximum number of entities to list',
                    default: '25',
                ),
            ],
            handler: [EntityListHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'entity-type:list',
            description: 'List all registered entity types',
            handler: [EntityTypeListHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'type:enable',
            description: 'Re-enable a previously disabled content type',
            arguments: [
                new ArgumentDefinition(
                    name: 'type',
                    mode: ArgumentMode::Required,
                    description: 'The entity type ID to enable (e.g. note)',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'actor',
                    mode: OptionMode::Required,
                    description: 'Actor ID for the audit log',
                    default: 'cli',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    mode: OptionMode::Required,
                    description: 'Tenant ID (optional, for per-tenant enable)',
                    default: '',
                ),
            ],
            handler: [TypeEnableHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'type:disable',
            description: 'Disable a registered content type (does not delete it)',
            arguments: [
                new ArgumentDefinition(
                    name: 'type',
                    mode: ArgumentMode::Required,
                    description: 'The entity type ID to disable (e.g. note)',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'actor',
                    mode: OptionMode::Required,
                    description: 'Actor ID for the audit log',
                    default: 'cli',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    mode: OptionMode::Required,
                    description: 'Tenant ID (optional, for per-tenant disable)',
                    default: '',
                ),
                new OptionDefinition(
                    name: 'force',
                    mode: OptionMode::None,
                    description: 'Allow disabling the last enabled type for the tenant',
                ),
                new OptionDefinition(
                    name: 'yes',
                    shortcut: 'y',
                    mode: OptionMode::None,
                    description: 'Skip confirmation prompt',
                ),
            ],
            handler: [TypeDisableHandler::class, 'execute'],
        );
    }
}

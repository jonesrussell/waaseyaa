<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\MakeEntityHandler;
use Waaseyaa\CLI\Handler\MakeJobHandler;
use Waaseyaa\CLI\Handler\MakeListenerHandler;
use Waaseyaa\CLI\Handler\MakeMigrationHandler;
use Waaseyaa\CLI\Handler\MakePolicyHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class MakeServiceProviderA extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'make:entity',
            description: 'Generate a content entity class',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The entity class name (e.g. "Article")',
                ),
            ],
            handler: [MakeEntityHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'make:job',
            description: 'Generate a queue job class',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The job class name (e.g. "ProcessUpload")',
                ),
            ],
            handler: [MakeJobHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'make:listener',
            description: 'Generate an event listener class',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The listener class name (e.g. "NotifyOnPublish")',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'event',
                    mode: OptionMode::Required,
                    description: 'The event class to listen for',
                    default: 'object',
                ),
                new OptionDefinition(
                    name: 'async',
                    mode: OptionMode::None,
                    description: 'Mark the listener as async',
                ),
            ],
            handler: [MakeListenerHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'make:migration',
            description: 'Generate a migration file',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The migration name (e.g. "create_comments_table")',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'create',
                    mode: OptionMode::Required,
                    description: 'Table name to create',
                ),
                new OptionDefinition(
                    name: 'table',
                    mode: OptionMode::Required,
                    description: 'Existing table name to modify',
                ),
                new OptionDefinition(
                    name: 'package',
                    mode: OptionMode::Required,
                    description: 'Package name to write migration to (e.g. "waaseyaa/node")',
                ),
                new OptionDefinition(
                    name: 'add-translations',
                    mode: OptionMode::Required,
                    description: 'Generate a translation-promotion migration for the given entity type id (FR-050)',
                ),
                new OptionDefinition(
                    name: 'default-langcode',
                    mode: OptionMode::Required,
                    description: 'Default langcode for the translation-promotion migration. Required when --add-translations is used (FR-051)',
                ),
            ],
            handler: [MakeMigrationHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'make:policy',
            description: 'Generate an access policy class',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The policy class name (e.g. "ContentPolicy")',
                ),
            ],
            handler: [MakePolicyHandler::class, 'execute'],
        );
    }
}

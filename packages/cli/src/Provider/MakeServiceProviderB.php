<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\MakeEntityTypeHandler;
use Waaseyaa\CLI\Handler\MakePluginHandler;
use Waaseyaa\CLI\Handler\MakeProviderHandler;
use Waaseyaa\CLI\Handler\MakePublicHandler;
use Waaseyaa\CLI\Handler\MakeTestHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class MakeServiceProviderB extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'make:provider',
            description: 'Generate a service provider class',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The provider class name (e.g. "Blog" or "BlogServiceProvider")',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'domain',
                    shortcut: 'd',
                    mode: OptionMode::None,
                    description: 'Generate a domain provider with entity type registration boilerplate',
                ),
            ],
            handler: [MakeProviderHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'make:public',
            description: 'Scaffold the canonical public/index.php front controller',
            options: [
                new OptionDefinition(
                    name: 'force',
                    mode: OptionMode::None,
                    description: 'Overwrite an existing public/index.php',
                ),
            ],
            handler: [MakePublicHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'make:test',
            description: 'Generate a PHPUnit test class',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The test class name (e.g. "NodeRepositoryTest")',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'unit',
                    mode: OptionMode::None,
                    description: 'Generate a unit test (default is integration)',
                ),
            ],
            handler: [MakeTestHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'make:entity-type',
            description: 'Generate an entity type class',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The entity type name (e.g. "event")',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'content',
                    mode: OptionMode::None,
                    description: 'Generate a content entity (default is config entity)',
                ),
            ],
            handler: [MakeEntityTypeHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'make:plugin',
            description: 'Generate a plugin class with #[WaaseyaaPlugin] attribute',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Required,
                    description: 'The plugin name (e.g. "my_formatter")',
                ),
            ],
            handler: [MakePluginHandler::class, 'execute'],
        );
    }
}

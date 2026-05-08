<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\ExtensionScaffoldHandler;
use Waaseyaa\CLI\Handler\RelationshipTypeScaffoldHandler;
use Waaseyaa\CLI\Handler\ScaffoldAuthHandler;
use Waaseyaa\CLI\Handler\WorkflowScaffoldHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class OtherScaffoldsServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'scaffold:relationship',
            description: 'Generate deterministic relationship-type scaffold JSON',
            options: [
                new OptionDefinition(
                    name: 'id',
                    mode: OptionMode::Required,
                    description: 'Relationship type machine name',
                ),
                new OptionDefinition(
                    name: 'label',
                    mode: OptionMode::Required,
                    description: 'Relationship type label',
                ),
                new OptionDefinition(
                    name: 'directionality',
                    mode: OptionMode::Required,
                    description: 'directed or bidirectional',
                    default: 'directed',
                ),
                new OptionDefinition(
                    name: 'inverse',
                    mode: OptionMode::Required,
                    description: 'Optional inverse relationship type ID',
                ),
                new OptionDefinition(
                    name: 'default-status',
                    mode: OptionMode::Required,
                    description: 'Default publication status (0/1)',
                    default: '1',
                ),
            ],
            handler: [RelationshipTypeScaffoldHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'scaffold:workflow',
            description: 'Generate deterministic workflow scaffold JSON',
            options: [
                new OptionDefinition(
                    name: 'id',
                    mode: OptionMode::Required,
                    description: 'Workflow machine name',
                ),
                new OptionDefinition(
                    name: 'bundle',
                    mode: OptionMode::Required,
                    description: 'Bundle ID the workflow applies to',
                ),
                new OptionDefinition(
                    name: 'state',
                    mode: OptionMode::Array_,
                    description: 'State IDs (repeatable)',
                ),
                new OptionDefinition(
                    name: 'transition',
                    mode: OptionMode::Array_,
                    description: 'Transition in id:from:to:permission form (repeatable)',
                ),
            ],
            handler: [WorkflowScaffoldHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'scaffold:extension',
            description: 'Generate deterministic external extension SDK scaffold JSON',
            options: [
                new OptionDefinition(
                    name: 'id',
                    mode: OptionMode::Required,
                    description: 'Plugin ID (machine name)',
                ),
                new OptionDefinition(
                    name: 'label',
                    mode: OptionMode::Required,
                    description: 'Plugin label',
                ),
                new OptionDefinition(
                    name: 'package',
                    mode: OptionMode::Required,
                    description: 'Composer package name (vendor/package)',
                ),
                new OptionDefinition(
                    name: 'namespace',
                    mode: OptionMode::Required,
                    description: 'Root PHP namespace (auto-derived from package when omitted)',
                ),
                new OptionDefinition(
                    name: 'class',
                    mode: OptionMode::Required,
                    description: 'Extension class name',
                    default: 'KnowledgeExtension',
                ),
                new OptionDefinition(
                    name: 'description',
                    mode: OptionMode::Required,
                    description: 'Plugin description',
                    default: 'External knowledge tooling extension',
                ),
                new OptionDefinition(
                    name: 'workflow-tag',
                    mode: OptionMode::Required,
                    description: 'Default workflow tag',
                    default: 'external-extension',
                ),
                new OptionDefinition(
                    name: 'relationship-type',
                    mode: OptionMode::Required,
                    description: 'Default traversal relationship type',
                    default: 'related',
                ),
                new OptionDefinition(
                    name: 'discovery-hint',
                    mode: OptionMode::Required,
                    description: 'Default discovery hint',
                    default: 'external-discovery-hint',
                ),
            ],
            handler: [ExtensionScaffoldHandler::class, 'execute'],
        );

        $projectRoot = $this->projectRoot !== '' ? $this->projectRoot : (string) getcwd();
        $scaffoldAuthHandler = new ScaffoldAuthHandler(projectRoot: $projectRoot);

        yield new CommandDefinition(
            name: 'scaffold:auth',
            description: 'Copy framework auth UI files into your app for customization',
            options: [
                new OptionDefinition(
                    name: 'force',
                    shortcut: 'f',
                    mode: OptionMode::None,
                    description: 'Overwrite existing files',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Show what would be copied without writing',
                ),
            ],
            handler: \Closure::fromCallable([$scaffoldAuthHandler, 'execute']),
        );
    }
}

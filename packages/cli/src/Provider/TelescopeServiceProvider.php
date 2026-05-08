<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\TelescopeClearHandler;
use Waaseyaa\CLI\Handler\TelescopeListHandler;
use Waaseyaa\CLI\Handler\TelescopePruneHandler;
use Waaseyaa\CLI\Handler\TelescopeValidateHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class TelescopeServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'telescope',
            description: 'List recent telescope entries',
            options: [
                new OptionDefinition(
                    name: 'type',
                    shortcut: 't',
                    mode: OptionMode::Required,
                    description: 'Filter by entry type (query, event, request, cache, job, exception)',
                ),
                new OptionDefinition(
                    name: 'limit',
                    shortcut: 'l',
                    mode: OptionMode::Required,
                    description: 'Maximum entries to show',
                    default: '20',
                ),
                new OptionDefinition(
                    name: 'slow',
                    mode: OptionMode::Required,
                    description: 'Show only slow queries exceeding threshold in ms',
                ),
            ],
            handler: [TelescopeListHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'telescope:clear',
            description: 'Clear all telescope entries',
            options: [
                new OptionDefinition(
                    name: 'type',
                    shortcut: 't',
                    mode: OptionMode::Required,
                    description: 'Clear only entries of a specific type',
                ),
            ],
            handler: [TelescopeClearHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'telescope:prune',
            description: 'Prune telescope entries older than retention period',
            options: [
                new OptionDefinition(
                    name: 'hours',
                    mode: OptionMode::Required,
                    description: 'Prune entries older than N hours',
                    default: '24',
                ),
            ],
            handler: [TelescopePruneHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'telescope:validate',
            description: 'Validate codified context for a session and compute drift score',
            arguments: [
                new ArgumentDefinition(
                    name: 'session_id',
                    mode: ArgumentMode::Required,
                    description: 'Session ID to validate',
                ),
                new ArgumentDefinition(
                    name: 'output_file',
                    mode: ArgumentMode::Optional,
                    description: 'Path to write validation report JSON',
                ),
            ],
            handler: [TelescopeValidateHandler::class, 'execute'],
        );
    }
}

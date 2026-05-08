<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\AboutHandler;
use Waaseyaa\CLI\Handler\AdminBuildHandler;
use Waaseyaa\CLI\Handler\AdminDevHandler;
use Waaseyaa\CLI\Handler\DebugContextHandler;
use Waaseyaa\CLI\Handler\EventListHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class MiscAServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void
    {
        $this->singleton(EventListHandler::class, function (): EventListHandler {
            /** @var ContractsEventDispatcherInterface $dispatcher */
            $dispatcher = $this->resolve(ContractsEventDispatcherInterface::class);

            if (!$dispatcher instanceof EventDispatcherInterface) {
                throw new \RuntimeException('EventListHandler requires Symfony EventDispatcherInterface.');
            }

            return new EventListHandler(dispatcher: $dispatcher);
        });
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'about',
            description: 'Display information about the Waaseyaa installation',
            handler: \Closure::fromCallable([new AboutHandler(), 'execute']),
        );

        $projectRoot = $this->projectRoot !== '' ? $this->projectRoot : (string) getcwd();

        yield new CommandDefinition(
            name: 'admin:build',
            description: 'Build the Nuxt admin SPA for static hosting (npm run generate)',
            handler: \Closure::fromCallable([new AdminBuildHandler(projectRoot: $projectRoot), 'execute']),
        );

        yield new CommandDefinition(
            name: 'admin:dev',
            description: 'Run the Nuxt admin SPA in development (npm run dev)',
            handler: \Closure::fromCallable([new AdminDevHandler(projectRoot: $projectRoot), 'execute']),
        );

        yield new CommandDefinition(
            name: 'debug:context',
            description: 'Render deterministic debug panels for workflow, traversal, and SSR context',
            options: [
                new OptionDefinition(
                    name: 'entity-type',
                    mode: OptionMode::Required,
                    description: 'Source entity type',
                    default: 'node',
                ),
                new OptionDefinition(
                    name: 'entity-id',
                    mode: OptionMode::Required,
                    description: 'Source entity ID',
                    default: '1',
                ),
                new OptionDefinition(
                    name: 'workflow-state',
                    mode: OptionMode::Required,
                    description: 'Workflow state',
                    default: 'draft',
                ),
                new OptionDefinition(
                    name: 'status',
                    mode: OptionMode::Required,
                    description: 'Status flag (0/1)',
                    default: '0',
                ),
                new OptionDefinition(
                    name: 'relationship-counts',
                    mode: OptionMode::Required,
                    description: 'Traversal counts in outbound:inbound form',
                    default: '0:0',
                ),
                new OptionDefinition(
                    name: 'view-mode',
                    mode: OptionMode::Required,
                    description: 'SSR view mode',
                    default: 'full',
                ),
                new OptionDefinition(
                    name: 'preview',
                    mode: OptionMode::Required,
                    description: 'SSR preview mode (0/1)',
                    default: '0',
                ),
            ],
            handler: [DebugContextHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'event:list',
            description: 'List all registered events and listeners',
            handler: [EventListHandler::class, 'execute'],
        );
    }
}

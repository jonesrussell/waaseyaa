<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\BundleScaffoldHandler;
use Waaseyaa\CLI\Handler\FixtureGenerateHandler;
use Waaseyaa\CLI\Handler\FixturePackRefreshHandler;
use Waaseyaa\CLI\Handler\FixtureScaffoldHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class BundleFixtureServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'scaffold:bundle',
            description: 'Generate deterministic bundle scaffold JSON',
            options: [
                new OptionDefinition(
                    name: 'id',
                    mode: OptionMode::Required,
                    description: 'Bundle machine name',
                ),
                new OptionDefinition(
                    name: 'label',
                    mode: OptionMode::Required,
                    description: 'Bundle label',
                ),
                new OptionDefinition(
                    name: 'entity-type',
                    mode: OptionMode::Required,
                    description: 'Entity type ID',
                    default: 'node',
                ),
                new OptionDefinition(
                    name: 'workflow',
                    mode: OptionMode::Required,
                    description: 'Workflow config ID',
                    default: 'editorial_default',
                ),
                new OptionDefinition(
                    name: 'field',
                    mode: OptionMode::Array_,
                    description: 'Field definition in name:type:required form (repeatable)',
                ),
            ],
            handler: [BundleScaffoldHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'fixture:scaffold',
            description: 'Generate deterministic workflow + relationship fixture scenario JSON',
            options: [
                new OptionDefinition(
                    name: 'key',
                    mode: OptionMode::Required,
                    description: 'Scenario node key (machine name)',
                ),
                new OptionDefinition(
                    name: 'title',
                    mode: OptionMode::Required,
                    description: 'Scenario node title',
                ),
                new OptionDefinition(
                    name: 'bundle',
                    mode: OptionMode::Required,
                    description: 'Node bundle/type',
                    default: 'article',
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
                    description: 'Publication status override (0/1)',
                ),
                new OptionDefinition(
                    name: 'uid',
                    mode: OptionMode::Required,
                    description: 'Fixture author UID',
                    default: '1',
                ),
                new OptionDefinition(
                    name: 'timestamp',
                    mode: OptionMode::Required,
                    description: 'Deterministic fixture timestamp',
                    default: '1735689600',
                ),
                new OptionDefinition(
                    name: 'relationship-type',
                    mode: OptionMode::Required,
                    description: 'Optional relationship type',
                ),
                new OptionDefinition(
                    name: 'to-key',
                    mode: OptionMode::Required,
                    description: 'Optional target fixture key for relationship',
                ),
                new OptionDefinition(
                    name: 'relationship-status',
                    mode: OptionMode::Required,
                    description: 'Relationship status (0/1)',
                    default: '1',
                ),
                new OptionDefinition(
                    name: 'output',
                    shortcut: 'o',
                    mode: OptionMode::Required,
                    description: 'Optional output file path (.json)',
                ),
            ],
            handler: [FixtureScaffoldHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'fixture:generate',
            description: 'Generate deterministic fixture scenario JSON from topology templates',
            options: [
                new OptionDefinition(
                    name: 'template',
                    mode: OptionMode::Required,
                    description: 'Template: fanout, chain, mixed-workflow',
                ),
                new OptionDefinition(
                    name: 'count',
                    mode: OptionMode::Required,
                    description: 'Node count',
                    default: '8',
                ),
                new OptionDefinition(
                    name: 'prefix',
                    mode: OptionMode::Required,
                    description: 'Node key prefix',
                    default: 'fixture',
                ),
                new OptionDefinition(
                    name: 'bundle',
                    mode: OptionMode::Required,
                    description: 'Node bundle/type',
                    default: 'article',
                ),
                new OptionDefinition(
                    name: 'timestamp',
                    mode: OptionMode::Required,
                    description: 'Deterministic base timestamp',
                    default: '1735689600',
                ),
                new OptionDefinition(
                    name: 'output',
                    shortcut: 'o',
                    mode: OptionMode::Required,
                    description: 'Optional output file path (.json)',
                ),
            ],
            handler: [FixtureGenerateHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'fixture:pack:refresh',
            description: 'Refresh deterministic fixture-pack aggregate from scenario JSON files',
            options: [
                new OptionDefinition(
                    name: 'input-dir',
                    mode: OptionMode::Required,
                    description: 'Directory containing scenario .json files',
                    default: 'tests/fixtures/scenarios',
                ),
                new OptionDefinition(
                    name: 'output',
                    shortcut: 'o',
                    mode: OptionMode::Required,
                    description: 'Output aggregate JSON path',
                ),
                new OptionDefinition(
                    name: 'json',
                    mode: OptionMode::None,
                    description: 'Print aggregate JSON to stdout',
                ),
                new OptionDefinition(
                    name: 'fail-on-empty',
                    mode: OptionMode::None,
                    description: 'Return non-zero if no scenarios are found',
                ),
            ],
            handler: [FixturePackRefreshHandler::class, 'execute'],
        );
    }
}

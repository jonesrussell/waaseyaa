<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\Command\Import\ImportResumeCommand;
use Waaseyaa\CLI\Command\Import\ImportRunAllCommand;
use Waaseyaa\CLI\Command\Import\ImportRunCommand;
use Waaseyaa\CLI\Command\Import\ImportStatusCommand;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\MigrationRunState;
use Waaseyaa\Migration\Runner\MigrationRunner;

/**
 * Registers the four `import:*` commands.
 *
 * - `import:run` / `import:run-all` / `import:status` ship with WP06.
 * - `import:resume` (FR-037) lands with WP07.
 *
 * Collaborator bindings ({@see MigrationRunner}, {@see MigrationRegistry},
 * {@see MigrationIdMap}, {@see MigrationRunState}) live in the migration
 * package's own {@see \Waaseyaa\Migration\ServiceProvider}; this provider
 * only binds the thin command handler classes (which the CLI kernel
 * container resolves via `[Class, method]` handler references) and yields
 * the {@see CommandDefinition}s.
 */
final class ImportServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void
    {
        $this->singleton(ImportRunCommand::class, function (): ImportRunCommand {
            $runner = $this->resolve(MigrationRunner::class);
            \assert($runner instanceof MigrationRunner);
            $registry = $this->resolve(MigrationRegistry::class);
            \assert($registry instanceof MigrationRegistry);

            return new ImportRunCommand($runner, $registry);
        });

        $this->singleton(ImportRunAllCommand::class, function (): ImportRunAllCommand {
            $runner = $this->resolve(MigrationRunner::class);
            \assert($runner instanceof MigrationRunner);
            $registry = $this->resolve(MigrationRegistry::class);
            \assert($registry instanceof MigrationRegistry);

            return new ImportRunAllCommand($runner, $registry);
        });

        $this->singleton(ImportStatusCommand::class, function (): ImportStatusCommand {
            $registry = $this->resolve(MigrationRegistry::class);
            \assert($registry instanceof MigrationRegistry);
            $idMap = $this->resolve(MigrationIdMap::class);
            \assert($idMap instanceof MigrationIdMap);
            $runState = $this->resolve(MigrationRunState::class);
            \assert($runState instanceof MigrationRunState);

            return new ImportStatusCommand($registry, $idMap, $runState);
        });

        $this->singleton(ImportResumeCommand::class, function (): ImportResumeCommand {
            $runner = $this->resolve(MigrationRunner::class);
            \assert($runner instanceof MigrationRunner);
            $registry = $this->resolve(MigrationRegistry::class);
            \assert($registry instanceof MigrationRegistry);

            return new ImportResumeCommand($runner, $registry);
        });
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'import:run',
            description: 'Run a single migration end-to-end (FR-032).',
            arguments: [
                new ArgumentDefinition(
                    name: 'migration_id',
                    mode: ArgumentMode::Required,
                    description: 'Id of the migration to execute (e.g. wp_users_to_accounts).',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Execute source + process steps; skip destination writes (FR-039).',
                ),
                new OptionDefinition(
                    name: 'halt-on-error',
                    mode: OptionMode::None,
                    description: 'Halt on the first per-record error (FR-047).',
                ),
                new OptionDefinition(
                    name: 'limit',
                    mode: OptionMode::Required,
                    description: 'Process only the first N source records (FR-040).',
                ),
                new OptionDefinition(
                    name: 'run-id',
                    mode: OptionMode::Required,
                    description: 'Override the generated UUIDv7 run id (advanced; CI/testing).',
                ),
            ],
            handler: [ImportRunCommand::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'import:run-all',
            description: 'Run every registered migration in dependency order (FR-033).',
            options: [
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Execute source + process steps; skip destination writes (FR-039).',
                ),
                new OptionDefinition(
                    name: 'halt-on-error',
                    mode: OptionMode::None,
                    description: 'Halt on the first per-record error in any migration (FR-047).',
                ),
                new OptionDefinition(
                    name: 'limit',
                    mode: OptionMode::Required,
                    description: 'Per-migration record cap (FR-040).',
                ),
            ],
            handler: [ImportRunAllCommand::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'import:status',
            description: 'Report per-migration import state (FR-034).',
            arguments: [
                new ArgumentDefinition(
                    name: 'migration_id',
                    mode: ArgumentMode::Optional,
                    description: 'Optional migration id to filter on; default lists all migrations.',
                ),
            ],
            handler: [ImportStatusCommand::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'import:resume',
            description: 'Resume the most recent run of one migration (FR-037).',
            arguments: [
                new ArgumentDefinition(
                    name: 'migration_id',
                    mode: ArgumentMode::Required,
                    description: 'Id of the migration to resume.',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Execute source + process steps; skip destination writes (FR-039).',
                ),
                new OptionDefinition(
                    name: 'halt-on-error',
                    mode: OptionMode::None,
                    description: 'Halt on the first per-record error (FR-047).',
                ),
                new OptionDefinition(
                    name: 'limit',
                    mode: OptionMode::Required,
                    description: 'Process only the next N source records (FR-040).',
                ),
            ],
            handler: [ImportResumeCommand::class, 'execute'],
        );
    }
}

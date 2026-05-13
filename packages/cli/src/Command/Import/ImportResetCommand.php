<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Import;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\MigrationRunState;

/**
 * `bin/waaseyaa import:reset <migration-id>` — clear the id-map (and
 * progress run-state) for one migration without touching destination
 * entities (FR-036).
 *
 * Use case: operators recovering from drift — the destination entities
 * exist but the source-side keys have shifted, so future re-imports
 * should re-create rows under fresh `source_id_hash`es. Compare with
 * `import:rollback`, which DELETES the destination entities.
 *
 * Destructive operation: requires `--confirm` to proceed. Without it,
 * the command prints a count and a hint and exits 0.
 *
 * Exit codes:
 *  - 0 — always (operator-driven destructive op; no per-record failures).
 *  - 2 — usage error (missing argument, unknown migration id).
 *
 * @api
 *
 * @spec FR-036 — `import:reset` clears id-map without touching entities
 */
final class ImportResetCommand
{
    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly MigrationIdMap $idMap,
        private readonly MigrationRunState $runState,
    ) {}

    public function execute(CliIO $io): int
    {
        $migrationId = $io->argument('migration_id');
        if (!\is_string($migrationId) || $migrationId === '') {
            $io->error('import:reset: argument "migration_id" is required.');
            return 2;
        }

        if (!$this->registry->has($migrationId)) {
            $io->error(\sprintf('import:reset: unknown migration "%s".', $migrationId));
            return 2;
        }

        $confirmed = (bool) $io->option('confirm');

        $idMapCount = $this->idMap->countForMigration($migrationId);

        if (!$confirmed) {
            $io->writeln(\sprintf(
                'WARNING: import:reset will delete %d id-map entr%s for migration "%s".',
                $idMapCount,
                $idMapCount === 1 ? 'y' : 'ies',
                $migrationId,
            ));
            $io->writeln('Destination entities will NOT be touched.');
            $io->writeln('Re-runs will re-import as new entities.');
            $io->writeln('Re-run with --confirm to proceed.');
            return 0;
        }

        $idMapDeleted = $this->idMap->deleteAllForMigration($migrationId);
        $runStateDeleted = $this->runState->deleteAllForMigration($migrationId);

        $io->writeln(\sprintf(
            '%s: reset complete (%d id-map rows + %d run-state rows deleted)',
            $migrationId,
            $idMapDeleted,
            $runStateDeleted,
        ));

        return 0;
    }
}

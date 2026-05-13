<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Import;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\Runner\RollbackReport;
use Waaseyaa\Migration\Runner\RollbackWalker;

/**
 * `bin/waaseyaa import:rollback <migration-id>` — undo every previously-
 * written record for one migration (FR-035, FR-043).
 *
 * Destructive operation: requires `--confirm` to proceed. Without it, the
 * command prints a count and a hint and exits 0 (no-op preview). With it,
 * the {@see RollbackWalker} walks the id-map in reverse-creation order
 * (FR-043), asks the destination plugin to delete each entity (FR-041),
 * and drops the id-map row on success.
 *
 * Best-effort semantics (FR-044): per-record failures are captured in
 * the {@see RollbackReport} but do NOT halt the walk. The exit code
 * reflects the report:
 *
 *  - 0 — every visited row rolled back cleanly.
 *  - 1 — at least one per-record failure recorded (the id-map row is
 *    preserved for those entries so the operator can retry).
 *  - 2 — usage error (missing argument, unknown migration id).
 *
 * @api
 *
 * @spec FR-035 — `import:rollback` entry point
 * @spec FR-043 — reverse-creation walk
 * @spec FR-044 — best-effort per-record rollback
 */
final class ImportRollbackCommand
{
    /** Cap on how many per-record errors render before the "...more" footer. */
    private const int ERROR_RENDER_CAP = 20;

    public function __construct(
        private readonly RollbackWalker $walker,
        private readonly MigrationRegistry $registry,
        private readonly MigrationIdMap $idMap,
    ) {}

    public function execute(CliIO $io): int
    {
        $migrationId = $io->argument('migration_id');
        if (!\is_string($migrationId) || $migrationId === '') {
            $io->error('import:rollback: argument "migration_id" is required.');
            return 2;
        }

        if (!$this->registry->has($migrationId)) {
            $io->error(\sprintf('import:rollback: unknown migration "%s".', $migrationId));
            return 2;
        }

        $confirmed = (bool) $io->option('confirm');

        if (!$confirmed) {
            $rowCount = $this->idMap->countForMigration($migrationId);
            $io->writeln(\sprintf(
                'WARNING: import:rollback will delete %d destination entit%s for migration "%s".',
                $rowCount,
                $rowCount === 1 ? 'y' : 'ies',
                $migrationId,
            ));
            $io->writeln('Re-run with --confirm to proceed.');
            return 0;
        }

        $report = $this->walker->rollback($migrationId);
        $this->renderReport($io, $report);

        return $report->failed > 0 ? 1 : 0;
    }

    private function renderReport(CliIO $io, RollbackReport $report): void
    {
        $io->writeln($report->summaryLine());

        if ($report->errors === []) {
            return;
        }

        $io->writeln('');
        $io->writeln('Errors:');
        $io->writeln(\sprintf(
            '  %-22s  %-32s  %-40s  message',
            'CODE',
            'ENTITY_TYPE',
            'DESTINATION_UUID',
        ));

        $rendered = 0;
        foreach ($report->errors as $error) {
            if ($rendered >= self::ERROR_RENDER_CAP) {
                break;
            }
            $io->writeln(\sprintf(
                '  %-22s  %-32s  %-40s  %s',
                $error->code,
                $error->destinationEntityType,
                $error->destinationUuid,
                $error->message,
            ));
            $rendered++;
        }

        $remaining = \count($report->errors) - $rendered;
        if ($remaining > 0) {
            $io->writeln(\sprintf(
                '  ... %d more error(s) (see entity.lifecycle log channel for the full trail).',
                $remaining,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Import;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\Exception\MigrationAbortedException;
use Waaseyaa\Migration\Runner\MigrationRunner;
use Waaseyaa\Migration\Runner\RunOptions;

/**
 * `bin/waaseyaa import:run-all` — walk every registered migration in
 * dependency-topological order.
 *
 * Per spec §9.1 and `contracts/cli-runner.md`:
 *
 *   - Walks {@see MigrationRegistry::topologicallySorted()}.
 *   - Per-migration record-level failures (FR-046) do NOT halt the walk;
 *     the loop moves on to the next migration with the highest exit code
 *     accumulated.
 *   - Run-level failures (FR-048; raised as {@see MigrationAbortedException}
 *     by the runner) DO halt the walk. Subsequent migrations are not
 *     attempted because their inputs cannot be trusted.
 *   - The final exit code is the maximum across per-migration exit codes
 *     (0 ⊕ 1 = 1; 1 ⊕ 5 = 5).
 *
 * Flags are passed through to every migration unchanged.
 *
 * @spec FR-033 — import:run-all
 */
final class ImportRunAllCommand
{
    public function __construct(
        private readonly MigrationRunner $runner,
        private readonly MigrationRegistry $registry,
    ) {}

    public function execute(CliIO $io): int
    {
        try {
            $options = $this->buildOptions($io);
        } catch (\InvalidArgumentException $e) {
            $io->error('import:run-all: ' . $e->getMessage());
            return 2;
        }

        $ordered = $this->registry->topologicallySorted();

        if ($ordered === []) {
            $io->writeln('No migrations registered.');
            return 0;
        }

        $aggImported = 0;
        $aggSkipped = 0;
        $aggFailed = 0;
        $migrationCount = 0;
        $worstExit = 0;

        foreach ($ordered as $definition) {
            $migrationCount++;

            try {
                $report = $this->runner->run($definition->id, $options);
            } catch (MigrationAbortedException $e) {
                $io->writeln($e->report->summaryLine());
                $io->error(\sprintf('Aborted: %s', $e->getMessage()));
                $aggImported += $e->report->imported;
                $aggSkipped += $e->report->skipped;
                $aggFailed += $e->report->failed;
                // FR-048: run-level failures DO halt run-all.
                $worstExit = \max($worstExit, 5);
                break;
            }

            $io->writeln($report->summaryLine());

            $aggImported += $report->imported;
            $aggSkipped += $report->skipped;
            $aggFailed += $report->failed;

            $exit = $report->failed > 0 ? 1 : 0;
            $worstExit = \max($worstExit, $exit);
        }

        $io->writeln('');
        $io->writeln(\sprintf(
            'Run-all: %d migration(s), %d imported, %d skipped, %d failed.',
            $migrationCount,
            $aggImported,
            $aggSkipped,
            $aggFailed,
        ));

        return $worstExit;
    }

    /**
     * @throws \InvalidArgumentException When `--limit` is malformed.
     */
    private function buildOptions(CliIO $io): RunOptions
    {
        $dryRun = (bool) $io->option('dry-run');
        $haltOnError = (bool) $io->option('halt-on-error');

        $limit = $io->option('limit');
        $parsedLimit = null;
        if ($limit !== null && $limit !== false && $limit !== '') {
            if (!\is_numeric($limit)) {
                throw new \InvalidArgumentException(
                    \sprintf('--limit must be a positive integer, got %s.', \var_export($limit, true)),
                );
            }
            $parsedLimit = (int) $limit;
        }

        return new RunOptions(
            dryRun: $dryRun,
            haltOnError: $haltOnError,
            limit: $parsedLimit,
            // Deliberately do NOT thread `--run-id` through `import:run-all`;
            // every migration must mint its own. Operators wanting a stable
            // run id should invoke `import:run` per migration.
            runId: null,
        );
    }
}

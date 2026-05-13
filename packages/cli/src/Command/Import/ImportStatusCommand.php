<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Import;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\MigrationIdMap;

/**
 * `bin/waaseyaa import:status [<migration-id>]` — surface per-migration state.
 *
 * WP06 ships the placeholder rendering described in spec §9.2:
 *
 *   - `STATE` is computed from `migration_id_map` row count vs source count.
 *     The richer "running"/"failed" semantics require `migration_run_state`
 *     (WP07); those columns surface as `0` / `'-'` until that ships. The
 *     `TODO(WP07)` comments below mark the wiring points.
 *
 *   - `TOTAL` is the source plugin's reported `count()` (or `?` if unknown).
 *
 *   - `IMPORTED` is {@see MigrationIdMap::countForMigration()} — the cheapest
 *     row-level signal available without touching the destination storage.
 *
 *   - `LAST RUN` is {@see MigrationIdMap::maxLastImportedAt()} — added on
 *     this WP as the smallest tractable surface (see WP06 tradeoff note).
 *
 * Exit code is always 0; `import:status` is informational.
 *
 * @spec FR-034 — import:status
 */
final class ImportStatusCommand
{
    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly MigrationIdMap $idMap,
    ) {}

    public function execute(CliIO $io): int
    {
        $filter = $io->argument('migration_id');
        $filter = \is_string($filter) && $filter !== '' ? $filter : null;

        $definitions = $this->registry->all();
        if ($filter !== null) {
            $definitions = \array_values(\array_filter(
                $definitions,
                static fn(MigrationDefinition $d): bool => $d->id === $filter,
            ));
            if ($definitions === []) {
                $io->error(\sprintf('import:status: unknown migration "%s".', $filter));
                return 2;
            }
        }

        // Header row matches spec §9.2 column ordering. Widths picked so the
        // common identifiers (e.g. `wp_comments_to_engagement`) fit without
        // wrapping; identifiers longer than 30 chars overflow gracefully (the
        // table is meant for operators, not for downstream parsers — `--format=json`
        // for that lands with WP07's `migration_run_state` integration).
        $io->writeln(\sprintf(
            '%-30s  %-10s  %6s  %8s  %6s  %7s  %s',
            'ID',
            'STATE',
            'TOTAL',
            'IMPORTED',
            'FAILED',
            'SKIPPED',
            'LAST RUN',
        ));

        foreach ($definitions as $definition) {
            $row = $this->statusRow($definition);
            $io->writeln(\sprintf(
                '%-30s  %-10s  %6s  %8s  %6s  %7s  %s',
                $row['id'],
                $row['state'],
                $row['total'],
                $row['imported'],
                $row['failed'],
                $row['skipped'],
                $row['last_run'],
            ));
        }

        return 0;
    }

    /**
     * Compute one display row for a single migration. Returns string-valued
     * cells so the calling sprintf renders a stable layout regardless of
     * whether a number is known.
     *
     * @return array{id: string, state: string, total: string, imported: string, failed: string, skipped: string, last_run: string}
     */
    private function statusRow(MigrationDefinition $definition): array
    {
        // Source `count()` is allowed to throw or return null per FR-005;
        // coerce both to "unknown".
        try {
            $sourceCount = $definition->source->count();
        } catch (\Throwable) {
            $sourceCount = null;
        }

        $importedCount = $this->idMap->countForMigration($definition->id);
        $lastRun = $this->idMap->maxLastImportedAt($definition->id);

        $state = match (true) {
            $importedCount === 0 => 'pending',
            // TODO(WP07): when migration_run_state lands, distinguish 'failed'
            // (last_run_exit >= 4), 'running' (lock held), and 'aborted' from
            // the partial/complete dichotomy below.
            $sourceCount !== null && $importedCount >= $sourceCount => 'complete',
            default => 'partial',
        };

        return [
            'id' => $definition->id,
            'state' => $state,
            'total' => $sourceCount === null ? '-' : (string) $sourceCount,
            'imported' => (string) $importedCount,
            // TODO(WP07): failed/skipped counts come from migration_run_state
            // aggregates. WP06 reports zero so the column shape is stable now
            // and downstream tooling (parsers, dashboards) does not need to
            // change shape between releases.
            'failed' => '0',
            'skipped' => '0',
            'last_run' => $lastRun ?? '-',
        ];
    }
}

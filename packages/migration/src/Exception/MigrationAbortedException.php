<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Exception;

use Waaseyaa\Migration\Runner\RunReport;

/**
 * Thrown by {@see \Waaseyaa\Migration\Runner\MigrationRunner::run()} when the
 * runner must halt mid-run.
 *
 * Two distinct halt conditions surface as this single exception type so
 * callers can render the same partial report regardless of why the run
 * stopped:
 *
 *  - `--halt-on-error` flag observed a per-record error and the operator
 *    asked the runner to short-circuit (FR-047).
 *  - A run-level error rose from the substrate (source plugin crashed mid
 *    iteration, destination crashed in a way that is not record-scoped,
 *    framework-level fault). These always halt regardless of flags (FR-048).
 *
 * The `$report` property carries the partial {@see RunReport} so the CLI
 * can render counters even when the run did not finish.
 *
 * @api
 *
 * @spec FR-045 — typed run-level halt exception
 * @spec FR-047 — `--halt-on-error` propagation
 * @spec FR-048 — run-level abort surfaces partial report
 */
final class MigrationAbortedException extends \RuntimeException
{
    /**
     * Stable string code shared by both halt paths. Operators distinguish
     * halt-on-error vs run-level via the wrapped `$previous` exception type.
     */
    public const string CODE = 'MIGRATION_ABORTED';

    /**
     * @param RunReport $report Partial report capturing whatever the runner managed before halting.
     * @param string $reason Operator-friendly explanation of the halt. Non-empty.
     * @param \Throwable|null $previous Underlying typed exception that triggered the halt (or null when raised programmatically).
     *
     * @throws \InvalidArgumentException When `$reason` is empty.
     */
    public function __construct(
        public readonly RunReport $report,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException(
                'MigrationAbortedException::$reason must be a non-empty string.',
            );
        }

        parent::__construct(\sprintf(
            "Migration '%s' aborted (run %s): %s",
            $report->migrationId,
            $report->runId,
            $reason,
        ), code: 0, previous: $previous);
    }
}

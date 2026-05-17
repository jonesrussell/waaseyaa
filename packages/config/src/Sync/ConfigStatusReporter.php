<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Produces an aggregate {@see StatusReport} summarising drift between the
 * sync store and the active store.
 *
 * Implementation strategy: delegate to {@see ConfigDiffer::diffAll()} so the
 * status logic stays in lock-step with the diff classification (FR-031:
 * "both sides serialize identically before diffing"). The reporter projects
 * each {@see DiffResult} into a {@see StatusEntry}, discarding the diff text
 * itself — the operator goes to `config:diff` for that.
 *
 * Read-only contract (FR-036): the reporter never mutates either store. It
 * walks the sync repository and the active-store snapshot; both are
 * read-only operations.
 *
 * Stability scope: INTERNAL. The CLI command consumes this and renders the
 * documented `config:status --format=plain|json` payload. Downstream
 * machine consumers should depend on the JSON output, not this PHP class.
 *
 * @api
 */
final class ConfigStatusReporter
{
    public function __construct(
        private readonly ConfigDiffer $differ,
    ) {}

    public function status(): StatusReport
    {
        $entries = [];
        foreach ($this->differ->diffAll() as $result) {
            $entries[] = new StatusEntry(
                ref: $result->ref,
                status: $result->status,
                renamedFrom: $result->renamedFrom,
            );
        }

        return new StatusReport(entries: $entries);
    }
}

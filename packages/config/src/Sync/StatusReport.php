<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Aggregate report produced by {@see ConfigStatusReporter::status()}.
 *
 * Counts are keyed by the {@see DiffResult}::STATUS_* values. `entries` is
 * the deterministically-sorted (alphabetical by ref) list of per-entity
 * classifications.
 *
 * Stability scope: INTERNAL. The `--format=json` payload uses the field names
 * documented in `contracts/cli-namespace.md` (`counts.in_sync`, `counts.drift`,
 * `counts.sync_only`, `counts.active_only`, `counts.renamed`, plus `entries`).
 * @api
 */
final readonly class StatusReport
{
    public const PER_ENTITY_TABLE_THRESHOLD = 50;

    /**
     * @param list<StatusEntry> $entries sorted alphabetically by ref
     */
    public function __construct(
        public array $entries,
    ) {
        // No further validation: entries are constructed by the reporter and
        // the StatusEntry constructor already validates statuses.
    }

    public function total(): int
    {
        return \count($this->entries);
    }

    /**
     * @return array{in_sync: int, drift: int, sync_only: int, active_only: int, renamed: int}
     */
    public function counts(): array
    {
        $counts = [
            'in_sync' => 0,
            'drift' => 0,
            'sync_only' => 0,
            'active_only' => 0,
            'renamed' => 0,
        ];
        foreach ($this->entries as $entry) {
            $counts[$entry->status]++;
        }

        return $counts;
    }

    public function hasDifferences(): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry->status !== DiffResult::STATUS_IN_SYNC) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the per-entity table should be rendered (count threshold).
     *
     * Threshold of 50 matches FR-034: "per-entity table when counts are
     * non-trivial (< 50 total entries)".
     */
    public function shouldRenderPerEntityTable(): bool
    {
        return $this->total() < self::PER_ENTITY_TABLE_THRESHOLD;
    }
}

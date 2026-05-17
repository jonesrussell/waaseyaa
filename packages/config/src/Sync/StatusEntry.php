<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * One row in a {@see StatusReport}: per-ref classification + optional rename
 * tracking.
 *
 * The `status` value is one of the {@see DiffResult}::STATUS_* constants.
 *
 * Stability scope: INTERNAL. The CLI `--format=json` payload re-serializes
 * these into the documented JSON shape (FR-035); downstream consumers should
 * consume that payload, not this PHP class directly.
 */
final readonly class StatusEntry
{
    public function __construct(
        public string $ref,
        public string $status,
        public ?string $renamedFrom = null,
    ) {
        if (!\in_array($status, [
            DiffResult::STATUS_IN_SYNC,
            DiffResult::STATUS_DRIFT,
            DiffResult::STATUS_SYNC_ONLY,
            DiffResult::STATUS_ACTIVE_ONLY,
            DiffResult::STATUS_RENAMED,
        ], true)) {
            throw new \InvalidArgumentException(sprintf(
                'StatusEntry status "%s" must be one of the DiffResult STATUS_* values.',
                $status,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Sync;

/**
 * Optional mapper extension for support diagnostics.
 *
 * Implement this when your mapper can explain *why* a hit was rejected.
 * NcSyncService uses this data for --explain output and JSON reports.
 */
interface NcHitSupportDiagnosticsInterface
{
    /**
     * Diagnose mapper support for a raw NorthCloud hit.
     *
     * Contract:
     * - `supported` MUST always be present.
     * - When `supported` is false, include a short machine-readable `reason`.
     * - `details` may contain arbitrary scalar metadata for observability output.
     *
     * @param array<string, mixed> $hit
     * @return array{supported: bool, reason?: string, details?: array<string, scalar|null>}
     */
    public function diagnoseSupport(array $hit): array;
}

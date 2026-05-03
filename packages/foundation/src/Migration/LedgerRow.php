<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration;

/**
 * One row of the `waaseyaa_migrations` ledger as a typed DTO.
 *
 * Returned by {@see MigrationRepository::allWithChecksums()} for the
 * verify CLI (WP10) and any other consumer that wants structured access
 * instead of the raw associative array.
 *
 * Pre-WP09 rows have `checksum === null` and `diffHash === null`. Post-
 * WP09 v2 rows have both populated. Post-WP09 legacy rows still have
 * both null — legacy migrations do not produce a canonical form.
 */
final readonly class LedgerRow
{
    public function __construct(
        public string $migration,
        public string $package,
        public int $batch,
        public ?string $checksum,
        public ?string $diffHash,
    ) {}
}

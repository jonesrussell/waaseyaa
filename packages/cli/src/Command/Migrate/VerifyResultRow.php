<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migrate;

/**
 * Per-migration outcome of {@see VerifyRunner::verify()}.
 *
 * `status` is one of `match | mismatch | unknown | orphan`. Both
 * checksum strings may be null for the `unknown` and `orphan` cases —
 * the formatter renders absent values as JSON null and as `—` in the
 * human-readable column.
 */
final readonly class VerifyResultRow
{
    public function __construct(
        public string $migration,
        public string $status,
        public ?string $storedChecksum,
        public ?string $computedChecksum,
    ) {}
}

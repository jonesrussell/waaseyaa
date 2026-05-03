<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration;

/**
 * Outcome of {@see MigrationRepository::verifyChecksum()}.
 *
 * - `Match` — the stored checksum equals the expected one.
 * - `Mismatch` — both are present but differ.
 * - `Unknown` — the ledger row exists but has a null checksum (a
 *   pre-WP09 apply, or a legacy migration that does not produce one).
 *   Verify mode treats this as "trust but log" per the backfill ADR.
 * - `Missing` — no ledger row exists for the requested migration id.
 *   The full verify CLI (WP10) maps this to "drift: expected applied
 *   but absent".
 */
enum VerifyResult: string
{
    case Match = 'match';
    case Mismatch = 'mismatch';
    case Unknown = 'unknown';
    case Missing = 'missing';
}

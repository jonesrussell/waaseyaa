<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration;

/**
 * Raised when an already-applied v2 migration is requested with a
 * different source checksum than the one recorded in the ledger.
 *
 * Per spec §6.2 / §15 Q2, this is the single loudest failure mode in
 * the apply path: the same `migration_id` cannot mean two different
 * structural intents, and silently re-applying would corrupt the audit
 * trail.
 *
 * **Production-only by default.** {@see \Waaseyaa\Foundation\Migration\Migrator}
 * throws this exception when constructed with `isProduction: true`
 * (the safe default). Development installs (`isProduction: false`) log
 * a warning via the optional logger and skip the re-apply silently.
 *
 * The diagnostic code `CHECKSUM_MISMATCH` is part of the operator
 * surface — runbooks and CI greps match on the string.
 */
final class ChecksumMismatchException extends \RuntimeException
{
    public const DIAGNOSTIC_CODE = 'CHECKSUM_MISMATCH';

    public function __construct(
        public readonly string $migration,
        public readonly string $stored,
        public readonly string $computed,
    ) {
        parent::__construct(sprintf(
            'Migration "%s" has stored checksum %s but the current source produces %s. Re-apply is refused in production. Either revert the source change or author a new migration_id.',
            $migration,
            $stored,
            $computed,
        ));
    }

    public function diagnosticCode(): string
    {
        return self::DIAGNOSTIC_CODE;
    }
}

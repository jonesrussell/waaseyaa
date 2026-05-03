<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migrate;

/**
 * Roll-up of {@see VerifyRunner::verify()} outcomes used by the
 * formatter and the CLI exit-code logic.
 *
 * Per WP10 T057, the command exits non-zero when {@see hasFailure()}
 * is true — that is, when *any* mismatch or orphan is present.
 * Unknowns are tolerated (they are pre-WP09 / legacy rows; see
 * `docs/adr/008-ledger-checksum-backfill.md`).
 */
final readonly class VerifySummary
{
    public function __construct(
        public int $match,
        public int $mismatch,
        public int $unknown,
        public int $orphan,
    ) {}

    public function hasFailure(): bool
    {
        return $this->mismatch > 0 || $this->orphan > 0;
    }

    public function total(): int
    {
        return $this->match + $this->mismatch + $this->unknown + $this->orphan;
    }
}

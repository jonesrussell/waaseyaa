<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

/**
 * Value object describing the retention rules for revision pruning.
 *
 * Used by {@see RevisionPruner} when (and if) pruning is enabled. Auto-pruning
 * is a NON-GOAL for the current milestone (spec §1.2 / §2.2); this class is
 * scaffolded here to reserve the surface for a later work package.
 *
 * ## Retention rules (any may be combined; the most permissive wins)
 *
 * - `keepLastN`: always keep the N most-recent revisions, regardless of age.
 * - `keepNewerThan`: keep revisions created after this threshold.
 * - `keepByAuthorRole`: keep all revisions authored by users with a given role
 *   (role string is application-defined).
 *
 * @api
 */
final class RevisionPruningPolicy
{
    /**
     * @param int|null         $keepLastN         Keep this many of the most-recent revisions. `null` = no limit.
     * @param \DateTimeImmutable|null $keepNewerThan Keep revisions newer than this date. `null` = no cutoff.
     * @param string[]         $keepByAuthorRole  Keep all revisions created by users with any of these role strings.
     */
    public function __construct(
        public readonly ?int $keepLastN = null,
        public readonly ?\DateTimeImmutable $keepNewerThan = null,
        public readonly array $keepByAuthorRole = [],
    ) {}

    /**
     * Build a default no-op policy (no pruning constraints).
     */
    public static function default(): self
    {
        return new self();
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Waaseyaa\Entity\RevisionableEntityInterface;

/**
 * Disabled scaffold for future revision pruning logic.
 *
 * Auto-pruning is explicitly a **NON-GOAL** for the current milestone
 * (spec §1.2 / §2.2). This class exists to reserve the public surface for a
 * later work package. The `$enabled` flag is `false` by default and cannot be
 * set to `true` via this scaffold — active pruning logic is not yet implemented.
 *
 * ## Activation path (future WP)
 *
 * 1. Inject the entity type, revision storage, and database into the constructor.
 * 2. Set `$enabled = true` via the named constructor.
 * 3. Implement the `prune()` body using `$policy` to filter candidate revisions.
 *
 * @api
 */
final class RevisionPruner
{
    private bool $enabled = false;

    public function __construct(
        private readonly RevisionPruningPolicy $policy = new RevisionPruningPolicy(),
    ) {}

    /**
     * Run a pruning pass for the given entity.
     *
     * **Currently a no-op.** Returns a {@see RevisionPruningReport::disabled()} report
     * immediately when `$enabled === false` (which is always true in this scaffold).
     *
     * Future implementations should:
     * 1. Enumerate revisions via {@see RevisionableEntityStorageInterface::listRevisions()}.
     * 2. Apply `$this->policy` rules to determine which revisions may be deleted.
     * 3. Delete candidates, skip protected revisions, and populate the report.
     *
     * @api
     */
    public function prune(RevisionableEntityInterface $entity): RevisionPruningReport
    {
        if (!$this->enabled) {
            return RevisionPruningReport::disabled();
        }

        // Active pruning logic is reserved for a future work package.
        // This branch is currently unreachable.
        return RevisionPruningReport::disabled(); // @codeCoverageIgnore
    }

    /**
     * Expose the configured policy (useful for inspection / testing).
     */
    public function policy(): RevisionPruningPolicy
    {
        return $this->policy;
    }
}

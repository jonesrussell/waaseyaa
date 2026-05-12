<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\RevisionableEntityInterface;

/**
 * Storage contract for entity types that support revisions.
 *
 * Mixed into per-entity-type storage when the entity type is revisionable
 * (`EntityTypeInterface::isRevisionable() === true`). Non-revisionable entity
 * types do NOT implement this interface.
 *
 * ## Scope (WP08)
 *
 * Covers load, list, and setCurrentRevision. Pruning is deferred to a later WP.
 * Per-revision access control (WP09) is not in scope here.
 *
 * @api
 */
interface RevisionableEntityStorageInterface
{
    /**
     * Load any historical revision by its revision id (vid).
     *
     * Bypasses the current-revision pointer on the primary table: the returned
     * entity may NOT be the current revision. Use {@see RevisionableEntityInterface::isCurrentRevision()}
     * to check.
     *
     * Returns `null` when no revision with the given id exists for this entity type.
     *
     * @api
     */
    public function loadRevision(
        EntityTypeInterface $type,
        int|string $revisionId,
    ): ?RevisionableEntityInterface;

    /**
     * Iterate all revisions for the given entity in descending `revision_created_at` order.
     *
     * Returns a lazy generator — the full result set is NOT loaded into memory at once.
     * Pagination is the caller's responsibility.
     *
     * @return iterable<RevisionableEntityInterface>
     *
     * @api
     */
    public function listRevisions(RevisionableEntityInterface $entity): iterable;

    /**
     * Re-point the primary table's current-revision pointer to an existing revision.
     *
     * Dispatches {@see \Waaseyaa\EntityStorage\Event\BeforeSaveEvent} before the write
     * and {@see \Waaseyaa\EntityStorage\Event\AfterSaveEvent} after successful commit.
     * AfterSaveEvent does NOT fire when the operation fails (rolls back).
     *
     * Wraps the pointer update in a transaction; rolls back and re-throws on any failure.
     *
     * @throws \InvalidArgumentException When the target revision id does not exist.
     *
     * @api
     */
    public function setCurrentRevision(
        RevisionableEntityInterface $entity,
        int|string $revisionId,
    ): void;
}

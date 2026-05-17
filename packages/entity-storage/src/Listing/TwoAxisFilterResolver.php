<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Listing;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Routes a `Filter::langcode($code)` listing result on a two-axis
 * (revisionable × translatable) entity type to the per-`(entity, langcode)`
 * current-revision pointer.
 *
 * M-007 already ships the canonical filter surface (`Filter::langcode()`),
 * the `entity:<type>:<id>:<langcode>` cache-tag emission, and the
 * `language.content` cache-context auto-injection. This resolver is the
 * **read-side glue** that satisfies the new contract M-004 added:
 *
 *  - **FR-031** — entities without a `(entity_id, $langcode)` translation row
 *    are excluded from the result. The driver's `read($type, $id, $langcode)`
 *    returns `null` when no translation row exists, so this resolver simply
 *    drops `null` after re-reading each candidate row.
 *  - **FR-033a** — each surviving row is materialised at the langcode's
 *    current revision, NOT at the entity-level "primary current revision."
 *    This is delegated to {@see EntityRepositoryInterface::find()} which
 *    routes the read through the per-`(entity, langcode)` pointer when a
 *    langcode is supplied.
 *
 * The resolver is intentionally a **post-filter** hook over an already-fetched
 * list of result rows. It is invoked by the M-007 listing pipeline (or any
 * caller) once the row set has been narrowed by criteria + access; calling it
 * for a single-axis (revisionable-only or translatable-only) type is a no-op,
 * since both FR-031 and FR-033a are vacuous in that case.
 *
 * Layer discipline: this class lives in L1 `entity-storage`. The L3 `listing`
 * package wires it into {@see \Waaseyaa\Listing\ListingResolver} via a service
 * provider; the dependency direction stays L3 → L1.
 *
 * Backwards compatibility: M-007's existing `Filter::langcode()` +
 * `ListingCacheInvalidator` behaviour on single-axis translatable types is
 * unchanged — the resolver only activates when the entity type reports
 * `isRevisionable() && isTranslatable()`.
 *
 * @api
 */
final class TwoAxisFilterResolver
{
    public function __construct(
        private readonly EntityRepositoryInterface $repository,
        private readonly EntityTypeInterface $entityType,
    ) {}

    /**
     * Re-read each row at the per-`(entity, langcode)` current revision and
     * exclude rows that have no translation row for `$langcode`.
     *
     * Order is preserved: the i-th non-null output corresponds to the i-th
     * surviving input. For single-axis entity types the input list is
     * returned unchanged.
     *
     * @param  list<EntityInterface> $rows    Pre-filtered candidate rows
     *                                        (criteria + access already applied)
     * @param  string                $langcode Canonical langcode token, e.g. `'oj'`
     * @return list<EntityInterface>          Rows materialised at the langcode's
     *                                        current revision; entities missing
     *                                        the translation are dropped.
     */
    public function resolveForLangcode(array $rows, string $langcode): array
    {
        if (!$this->appliesToEntityType()) {
            // Single-axis (revisionable-only or translatable-only) types
            // bypass the two-axis read path — both FR-031 and FR-033a are
            // vacuous, so leave the M-007 pipeline result untouched.
            return $rows;
        }

        if ($rows === [] || $langcode === '') {
            return $rows;
        }

        $resolved = [];
        foreach ($rows as $row) {
            $id = $row->id();
            if ($id === null) {
                // Defensive: rows without a persisted id can't be re-read.
                continue;
            }

            $idString = (string) $id;
            if ($idString === '') {
                continue;
            }

            // FR-033a — repository->find($id, $langcode) routes through the
            // per-(entity, langcode) current-revision pointer. FR-031 —
            // returns null when the (entity_id, $langcode) translation row
            // does not exist, naturally excluding the row from the result.
            $hydrated = $this->repository->find($idString, $langcode);
            if ($hydrated === null) {
                continue;
            }

            $resolved[] = $hydrated;
        }

        return $resolved;
    }

    /**
     * Whether the bound entity type is two-axis (revisionable × translatable).
     *
     * Public so callers (e.g. the L3 listing wiring) can decide whether to
     * invoke the resolver without paying the cost of an empty re-read pass.
     *
     * @api
     */
    public function appliesToEntityType(): bool
    {
        return $this->entityType->isRevisionable() && $this->entityType->isTranslatable();
    }
}

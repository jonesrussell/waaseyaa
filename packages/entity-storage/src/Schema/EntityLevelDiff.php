<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Schema;

use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;

/**
 * Entity-scoped wrapper around a {@see CompositeDiff} root plus a list
 * of per-bundle diffs.
 *
 * **Identity contract:**
 *
 * - `entityTypeId` is metadata only — it identifies which entity type
 *   the diff was computed for, but it is NOT part of structural
 *   identity. {@see checksum()} delegates to the root composite, so
 *   two diffs with different entity-type ids but identical structural
 *   content hash-equate. This is intentional (per spec §15 Q7's note
 *   that entity-type-id is a traceability label, not a structural
 *   discriminator).
 * - `composite` carries the base-table ops (column adds/alters/drops,
 *   indexes on the base table).
 * - `bundleDiffs` carries the per-bundle subtable ops, each scoped to
 *   `{base}__{bundle}`. Empty list means no bundle changes.
 *
 * **Layer:** Layer 1 (entity-storage). Imports the foundation
 * {@see CompositeDiff} value type — that direction is allowed
 * (downward); foundation never imports back.
 * @api
 */
final readonly class EntityLevelDiff
{
    /**
     * @param list<BundleLevelDiff> $bundleDiffs
     */
    public function __construct(
        public string $entityTypeId,
        public CompositeDiff $composite,
        public array $bundleDiffs = [],
    ) {}

    /**
     * @return array{entity_type_id: string, composite: array{ops: list<array<string, mixed>>}, bundles: list<array<string, mixed>>}
     */
    public function toCanonical(): array
    {
        return [
            'entity_type_id' => $this->entityTypeId,
            'composite' => $this->composite->toCanonical(),
            'bundles' => array_map(
                static fn(BundleLevelDiff $b): array => $b->toCanonical(),
                $this->bundleDiffs,
            ),
        ];
    }

    /**
     * Plan identity — delegates to the root composite per the §15 Q7
     * "entity-type-id is metadata, not structure" rule.
     */
    public function checksum(): string
    {
        return $this->composite->checksum();
    }

    public function isEmpty(): bool
    {
        return $this->composite->isEmpty() && $this->bundleDiffs === [];
    }
}

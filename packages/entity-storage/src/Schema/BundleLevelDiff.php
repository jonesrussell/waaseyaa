<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Schema;

use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;

/**
 * Subtable-scoped diff for a single bundle of an entity type.
 *
 * **Scope:** ops in `composite` touch ONLY the
 * `{baseTable}__{bundleId}` subtable. Base-table changes (e.g. adding
 * a core field) require an {@see EntityLevelDiff} that wraps both this
 * BundleLevelDiff AND the base-table ops in its own composite.
 *
 * **Subtable naming:** delegated to the centralized
 * {@see SqlSchemaHandler::resolveSubtableName()} (mission #1257 WP08).
 * Every consumer that materialises a subtable name MUST go through
 * that helper so the `{base}__{bundle}` separator stays canonical.
 */
final readonly class BundleLevelDiff
{
    public function __construct(
        public string $entityTypeId,
        public string $bundleId,
        public string $baseTable,
        public CompositeDiff $composite,
    ) {}

    public function subtableName(): string
    {
        return SqlSchemaHandler::resolveSubtableName($this->baseTable, $this->bundleId, $this->entityTypeId);
    }

    /**
     * @return array{entity_type_id: string, bundle_id: string, subtable: string, composite: array{ops: list<array<string, mixed>>}}
     */
    public function toCanonical(): array
    {
        return [
            'entity_type_id' => $this->entityTypeId,
            'bundle_id' => $this->bundleId,
            'subtable' => $this->subtableName(),
            'composite' => $this->composite->toCanonical(),
        ];
    }
}

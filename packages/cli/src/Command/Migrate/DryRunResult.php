<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migrate;

/**
 * Aggregate produced by {@see DryRunPlanner::plan()} — the ordered
 * list of nodes the migrator would touch on a real apply, paired with
 * counts for the formatter summary.
 */
final readonly class DryRunResult
{
    /**
     * @param list<DryRunNode> $nodes
     */
    public function __construct(public array $nodes) {}

    public function wouldApplyCount(): int
    {
        return count(array_filter($this->nodes, static fn(DryRunNode $n): bool => ! $n->alreadyApplied));
    }

    public function v2Count(): int
    {
        return count(array_filter($this->nodes, static fn(DryRunNode $n): bool => $n->kind === 'v2'));
    }

    public function legacyCount(): int
    {
        return count(array_filter($this->nodes, static fn(DryRunNode $n): bool => $n->kind === 'legacy'));
    }
}

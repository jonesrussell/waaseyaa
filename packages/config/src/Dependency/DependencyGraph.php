<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Dependency;

/**
 * Immutable directed acyclic graph (DAG) of config-entity dependencies plus
 * a precomputed topological ordering.
 *
 * Produced by {@see DependencyResolver::resolve()}. Tests may construct
 * instances directly with carefully chosen fixtures.
 *
 * Constructor invariants:
 *  - `$topologicalOrder` is a permutation of `array_keys($edges)` (complete).
 *  - The ordering is consistent with `$edges`: for every edge `u -> v` in
 *    `$edges`, `u` appears before `v` in `$topologicalOrder` (acyclic).
 *
 * Both invariants are enforced; passing inconsistent inputs throws
 * `\InvalidArgumentException`.
 *
 * Stability: INTERNAL — the class shape may evolve additively; production
 * consumers should obtain graphs via `DependencyResolver`.
 *
 * @api
 */
final readonly class DependencyGraph
{
    /**
     * @param array<string, list<string>> $edges            Adjacency list: ref => outgoing edges (dependents).
     *                                                      Each key is a `<entity_type>.<entity_id>` ref; each
     *                                                      value lists refs that depend on the key. Every ref
     *                                                      mentioned as a value MUST also exist as a key.
     * @param list<string>                $topologicalOrder Complete acyclic ordering of `array_keys($edges)`.
     *
     * @throws \InvalidArgumentException When invariants do not hold.
     */
    public function __construct(
        public array $edges,
        public array $topologicalOrder,
    ) {
        $this->assertInvariants();
    }

    /**
     * Return every node (ref) in the graph, in topological order.
     *
     * Equivalent to `$topologicalOrder` but exposed as a method for symmetry
     * with `edgesFrom()` and `hasNode()`.
     *
     * @return list<string>
     */
    public function nodes(): array
    {
        return $this->topologicalOrder;
    }

    /**
     * True if the given ref exists as a node in the graph.
     */
    public function hasNode(string $ref): bool
    {
        return \array_key_exists($ref, $this->edges);
    }

    /**
     * Outgoing edges from the given node (its dependents).
     *
     * @return list<string> Empty list when the node has no dependents or
     *                     does not exist in the graph.
     */
    public function edgesFrom(string $ref): array
    {
        return $this->edges[$ref] ?? [];
    }

    /**
     * Always `true` by construction — the constructor enforces acyclicity.
     *
     * Exposed for explicit, self-documenting consumer code.
     */
    public function isAcyclic(): bool
    {
        return true;
    }

    /**
     * Enforce constructor invariants.
     *
     * @throws \InvalidArgumentException
     */
    private function assertInvariants(): void
    {
        // 1. Every value mentioned in an adjacency list must exist as a key.
        foreach ($this->edges as $node => $outgoing) {
            if ($node === '') {
                throw new \InvalidArgumentException('DependencyGraph edges keys must be non-empty strings.');
            }
            foreach ($outgoing as $dependent) {
                if (!\array_key_exists($dependent, $this->edges)) {
                    throw new \InvalidArgumentException(\sprintf(
                        'DependencyGraph edge "%s" -> "%s" references a node not present in the edges map.',
                        $node,
                        $dependent,
                    ));
                }
            }
        }

        // 2. Topological order must be a permutation of edge keys.
        $expected = \array_keys($this->edges);
        if (\count($this->topologicalOrder) !== \count($expected)) {
            throw new \InvalidArgumentException(\sprintf(
                'DependencyGraph topological order has %d entries, expected %d (one per node).',
                \count($this->topologicalOrder),
                \count($expected),
            ));
        }
        $orderSet = \array_flip($this->topologicalOrder);
        if (\count($orderSet) !== \count($this->topologicalOrder)) {
            throw new \InvalidArgumentException('DependencyGraph topological order contains duplicates.');
        }
        foreach ($expected as $node) {
            if (!\array_key_exists($node, $orderSet)) {
                throw new \InvalidArgumentException(\sprintf(
                    'DependencyGraph topological order is missing node "%s".',
                    $node,
                ));
            }
        }

        // 3. Order must be acyclic relative to the edges: for every edge u -> v,
        //    position(u) < position(v).
        $position = \array_flip($this->topologicalOrder);
        foreach ($this->edges as $node => $outgoing) {
            $posU = $position[$node];
            foreach ($outgoing as $dependent) {
                $posV = $position[$dependent];
                if ($posU >= $posV) {
                    throw new \InvalidArgumentException(\sprintf(
                        'DependencyGraph topological order is inconsistent with edges: "%s" precedes "%s" but edge requires the reverse.',
                        $node,
                        $dependent,
                    ));
                }
            }
        }
    }
}

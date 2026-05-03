<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration\Dag;

/**
 * Unified dependency graph over legacy + v2 migration nodes.
 *
 * **Algorithm:** Kahn's topological sort. Whenever multiple nodes have
 * in-degree zero, the tie-break is `(package ASC, id ASC)` per spec
 * §15 Q4 — ASCII compare, locale-independent. This is the determinism
 * contract: the same node set produces the same ordered list on every
 * platform and PHP version.
 *
 * **Edge semantics:** a node N's `dependencies` strings are resolved
 * against the union of (every node id in the graph + every distinct
 * package name in the graph):
 *
 * - String matches a node id ⇒ edge that-node → N.
 * - String matches a package name ⇒ edge from EVERY node M with
 *   `M.package === string` and `M !== N` to N.
 * - String matches neither ⇒
 *   - V2 source node: throw {@see UnknownDependencyException} (strict).
 *   - Legacy source node: silently drop (preserves pre-WP06 semantics
 *     where `$after` accepted absent package names without error).
 *
 * **Cycle detection:** Kahn's algorithm exposes cycles naturally — if
 * any nodes remain after the queue empties, those nodes participate in
 * one or more cycles. The graph walks the residual to extract one
 * concrete cycle for the {@see MigrationCycleDetectedException}.
 *
 * Foundation-only (Layer 0). The graph holds back-references to
 * {@see \Waaseyaa\Foundation\Migration\Migration} (legacy abstract base)
 * and {@see \Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2}
 * (v2 contract) — both live in foundation, so no upward import.
 */
final readonly class MigrationGraph
{
    /**
     * @param list<MigrationNode>                  $nodes
     * @param array<string, list<string>>          $edges  source-id => list<dependent-id>
     * @param array<string, int>                   $inDegree node-id => count of incoming edges
     */
    private function __construct(
        public array $nodes,
        private array $edges,
        private array $inDegree,
    ) {}

    /**
     * @param list<MigrationNode> $nodes
     */
    public static function build(array $nodes): self
    {
        self::assertUniqueIds($nodes);

        $byId = [];
        $packages = [];
        foreach ($nodes as $node) {
            $byId[$node->id] = $node;
            $packages[$node->package] = true;
        }

        $edges = [];
        $inDegree = [];
        foreach ($nodes as $node) {
            $edges[$node->id] ??= [];
            $inDegree[$node->id] ??= 0;
        }

        foreach ($nodes as $node) {
            foreach ($node->dependencies as $dep) {
                $resolved = self::resolveDep($dep, $byId, $packages, $node);

                foreach ($resolved as $sourceId) {
                    if ($sourceId === $node->id) {
                        // Self-edge: ignored — a node can't depend on itself.
                        continue;
                    }
                    $edges[$sourceId][] = $node->id;
                    $inDegree[$node->id]++;
                }
            }
        }

        return new self($nodes, $edges, $inDegree);
    }

    /**
     * @return list<MigrationNode>
     */
    public function topologicalOrder(): array
    {
        $byId = [];
        foreach ($this->nodes as $node) {
            $byId[$node->id] = $node;
        }

        $inDegree = $this->inDegree;
        $ready = $this->collectReady($inDegree);

        $sorted = [];
        while ($ready !== []) {
            self::sortReady($ready);
            $current = array_shift($ready);
            $sorted[] = $byId[$current];

            foreach ($this->edges[$current] ?? [] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $ready[] = $dependent;
                }
            }
        }

        if (count($sorted) !== count($this->nodes)) {
            throw new MigrationCycleDetectedException($this->extractCycle($inDegree));
        }

        return $sorted;
    }

    /**
     * @param array<string, int> $inDegree
     * @return list<string>
     */
    private function collectReady(array $inDegree): array
    {
        $ready = [];
        foreach ($inDegree as $id => $count) {
            if ($count === 0) {
                $ready[] = $id;
            }
        }

        return $ready;
    }

    /**
     * Tie-break (package ASC, id ASC) on the ready list per Q4.
     *
     * @param list<string> $ready
     */
    private function sortReady(array &$ready): void
    {
        $byId = [];
        foreach ($this->nodes as $node) {
            $byId[$node->id] = $node;
        }

        usort(
            $ready,
            static fn(string $a, string $b): int =>
                [$byId[$a]->package, $byId[$a]->id] <=> [$byId[$b]->package, $byId[$b]->id],
        );
    }

    /**
     * @param array<string, MigrationNode> $byId
     * @param array<string, bool>          $packages
     * @return list<string>
     */
    private static function resolveDep(string $dep, array $byId, array $packages, MigrationNode $source): array
    {
        if (isset($byId[$dep])) {
            return [$dep];
        }

        if (isset($packages[$dep])) {
            $matches = [];
            foreach ($byId as $candidate) {
                if ($candidate->package === $dep) {
                    $matches[] = $candidate->id;
                }
            }
            return $matches;
        }

        if ($source->kind === MigrationKind::V2) {
            throw new UnknownDependencyException($dep, $source->id);
        }

        // Legacy: silently drop unknown deps to preserve pre-WP06
        // semantics where $after accepted absent package names.
        return [];
    }

    /**
     * @param list<MigrationNode> $nodes
     */
    private static function assertUniqueIds(array $nodes): void
    {
        $seen = [];
        foreach ($nodes as $node) {
            if (isset($seen[$node->id])) {
                throw new \InvalidArgumentException(sprintf(
                    'Duplicate migration id "%s" in MigrationGraph::build() — every node must have a unique ledger id.',
                    $node->id,
                ));
            }
            $seen[$node->id] = true;
        }
    }

    /**
     * Extract one concrete cycle from the residual nodes.
     *
     * @param array<string, int> $inDegree
     * @return list<string>
     */
    private function extractCycle(array $inDegree): array
    {
        $residual = [];
        foreach ($inDegree as $id => $count) {
            if ($count > 0) {
                $residual[$id] = true;
            }
        }

        if ($residual === []) {
            return [];
        }

        $start = array_key_first($residual);
        $path = [];
        $visited = [];
        $current = $start;

        while (true) {
            $path[] = $current;
            $visited[$current] = count($path) - 1;

            $next = null;
            foreach ($this->edges[$current] ?? [] as $candidate) {
                if (isset($residual[$candidate])) {
                    $next = $candidate;
                    break;
                }
            }

            if ($next === null) {
                // Dead-end residual — fall back to listing all stuck ids.
                return array_keys($residual);
            }

            if (isset($visited[$next])) {
                $cycleStart = $visited[$next];
                return array_slice($path, $cycleStart);
            }

            $current = $next;
        }
    }
}

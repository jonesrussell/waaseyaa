<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Dependency;

use Waaseyaa\Config\Dependency\Exception\ConfigDependencyCycleException;
use Waaseyaa\Config\Dependency\Exception\ConfigDependencyMissingException;

/**
 * Build a {@see DependencyGraph} from a flat list of declared dependencies.
 *
 * Input shape: a map of `<entity_type>.<entity_id>` ref to its dependencies
 * (the refs it depends on). Output: a DAG plus a deterministic topological
 * ordering (dependencies-first).
 *
 * Algorithm: DFS with white/gray/black coloring; on revisit of a gray node
 * raise {@see ConfigDependencyCycleException} with the reconstructed cycle
 * path. Missing-dependency edges raise
 * {@see ConfigDependencyMissingException}.
 *
 * Tie-break: within a "topological layer" (nodes whose remaining
 * dependencies have all been emitted) the order is lexicographic on the ref
 * string. This guarantees determinism across runs, processes, and OSes.
 *
 * Complexity: O(V + E) for DFS, plus O(V log V) for the lexicographic
 * tie-break. Memory: O(V + E).
 *
 * @api
 */
final class DependencyResolver
{
    /** White (unvisited) DFS coloring marker. */
    private const COLOR_WHITE = 0;

    /** Gray (on the current DFS stack) DFS coloring marker. */
    private const COLOR_GRAY = 1;

    /** Black (fully visited) DFS coloring marker. */
    private const COLOR_BLACK = 2;

    /**
     * Resolve a dependency declaration map into a DAG.
     *
     * The full graph is computed against `$declarations` as the node universe
     * plus, optionally, `$activeRefs` which lists refs known to exist in the
     * active store. A dependency on an active-store ref is satisfied without
     * adding that ref as a graph node — only refs in `$declarations` are
     * ordered for import.
     *
     * @param array<string, list<string>> $declarations Map of `<entity_type>.<entity_id>` ref to its
     *                                                 declared dependencies (each entry is also a ref).
     * @param list<string>                $activeRefs   Refs known to exist in the active store; treated as
     *                                                 satisfied prerequisites. Default: none.
     *
     * @throws ConfigDependencyCycleException   When the declarations form a cycle.
     * @throws ConfigDependencyMissingException When a declared dependency is in neither
     *                                          `$declarations` nor `$activeRefs`.
     */
    public function resolve(array $declarations, array $activeRefs = []): DependencyGraph
    {
        $activeSet = \array_flip($activeRefs);
        $nodes = \array_keys($declarations);
        \sort($nodes);

        // Build forward adjacency (ref -> dependents) and validate edge tails.
        $edges = [];
        foreach ($nodes as $ref) {
            $edges[$ref] = [];
        }
        foreach ($nodes as $ref) {
            foreach ($declarations[$ref] as $dependency) {
                if (!\array_key_exists($dependency, $declarations)) {
                    if (\array_key_exists($dependency, $activeSet)) {
                        // Dependency lives in active store; satisfied, no graph edge.
                        continue;
                    }
                    throw new ConfigDependencyMissingException(
                        missingRef: $dependency,
                        requiredBy: $ref,
                    );
                }
                // Edge points from dependency -> dependent (so DFS yields
                // dependencies-first post-order naturally).
                $edges[$dependency][] = $ref;
            }
        }

        // Deduplicate outgoing edges. The stored adjacency is kept in lex
        // order for stable iteration order in $graph->edgesFrom().
        foreach ($edges as $from => $outgoing) {
            $unique = \array_values(\array_unique($outgoing));
            \sort($unique);
            $edges[$from] = $unique;
        }

        // For DFS traversal we visit children in REVERSE lex order so that
        // post-order (then reversed) yields ascending lex order at each
        // topological level. The stored $edges adjacency keeps ascending
        // lex order for consumer-facing stability.
        $dfsAdjacency = [];
        foreach ($edges as $from => $outgoing) {
            $reversed = $outgoing;
            \rsort($reversed);
            $dfsAdjacency[$from] = $reversed;
        }

        // DFS from every node, lex order; detect cycles, collect post-order.
        $color = [];
        foreach ($nodes as $ref) {
            $color[$ref] = self::COLOR_WHITE;
        }
        $postOrder = [];

        foreach ($nodes as $start) {
            if ($color[$start] !== self::COLOR_WHITE) {
                continue;
            }
            $this->dfs($start, $dfsAdjacency, $color, $postOrder);
        }

        // Reverse post-order to get "dependencies first". The DFS above visits
        // edges that point from dependency -> dependent; post-order on those
        // edges yields dependents first, so reversing gives dependencies first.
        // Tie-break is achieved by traversing children in lex order; siblings
        // discovered later in the DFS appear earlier in post-order (last-in,
        // first-out), and reversing produces the lex-stable "first one first"
        // result.
        $topologicalOrder = \array_reverse($postOrder);

        return new DependencyGraph(edges: $edges, topologicalOrder: $topologicalOrder);
    }

    /**
     * DFS body — visit `$node`, recurse into outgoing edges, append to post-order.
     *
     * @param array<string, list<string>> $edges
     * @param array<string, int>          $color
     * @param list<string>                $postOrder
     *
     * @throws ConfigDependencyCycleException
     */
    private function dfs(string $node, array $edges, array &$color, array &$postOrder): void
    {
        // Iterative DFS to avoid PHP stack frame limits on deep graphs.
        // Each stack frame: [node, iteratorIndex, parentChain].
        /** @var list<array{0: string, 1: int}> $stack */
        $stack = [[$node, 0]];
        /** @var list<string> $pathStack */
        $pathStack = [];

        $color[$node] = self::COLOR_GRAY;
        $pathStack[] = $node;

        while ($stack !== []) {
            $top = \count($stack) - 1;
            [$current, $index] = $stack[$top];
            $children = $edges[$current];

            if ($index < \count($children)) {
                $stack[$top][1] = $index + 1;
                $next = $children[$index];
                $nextColor = $color[$next] ?? self::COLOR_WHITE;

                if ($nextColor === self::COLOR_GRAY) {
                    // Cycle: rebuild the path from where $next first appears in
                    // pathStack, then close with $next.
                    $cycleStart = \array_search($next, $pathStack, true);
                    if ($cycleStart === false) {
                        // Defensive — should be unreachable; gray implies on path.
                        $cycle = [$next, $next];
                    } else {
                        $cycle = \array_slice($pathStack, $cycleStart);
                        $cycle[] = $next;
                    }
                    throw new ConfigDependencyCycleException(cyclePath: $cycle);
                }

                if ($nextColor === self::COLOR_WHITE) {
                    $color[$next] = self::COLOR_GRAY;
                    $pathStack[] = $next;
                    $stack[] = [$next, 0];
                }
                // Black: already finished — skip.
                continue;
            }

            // No more children — finalize $current.
            $color[$current] = self::COLOR_BLACK;
            $postOrder[] = $current;
            \array_pop($pathStack);
            \array_pop($stack);
        }
    }
}

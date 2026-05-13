<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Discovery;

/**
 * Detects dependency cycles in a {@see DependencyGraph} (FR-015).
 *
 * Uses classical DFS with three-colour marking:
 *
 * - **WHITE** — vertex not yet visited.
 * - **GRAY** — vertex is on the current recursion stack (in-progress).
 * - **BLACK** — vertex and all its descendants are fully explored.
 *
 * A cycle is detected when DFS encounters a GRAY successor: the cycle path is
 * extracted by walking the recursion stack from that successor back to the
 * point of re-entry. The closing repeat is included so log readers can see the
 * loop (`a -> b -> c -> a`).
 *
 * Why DFS instead of Kahn's algorithm for cycle detection: Kahn's *detects*
 * cycles (by leaving non-zero in-degrees) but does not produce the offending
 * path. The spec mandates the cycle path in {@see \Waaseyaa\Migration\Exception\MigrationCycleException},
 * so DFS three-colour is the right tool. Topological order is the dual
 * problem and lives in {@see DependencyGraph::topologicalOrder()} where Kahn's
 * is preferable for its deterministic tie-breaking.
 *
 * @api
 */
final class CycleDetector
{
    private const int WHITE = 0;
    private const int GRAY = 1;
    private const int BLACK = 2;

    /**
     * Returns the first cycle path found, or `[]` when the graph is acyclic.
     * The returned path starts and ends with the same id (closing repeat) and
     * has length `>= 2`.
     *
     * Vertex traversal is lexicographically ordered so reported cycle paths
     * are deterministic across runs and across implementations.
     *
     * @return list<string>
     */
    public function detect(DependencyGraph $graph): array
    {
        $vertices = $graph->vertices();
        $adjacency = $graph->adjacency();

        // array_fill_keys returns `array<string, 0>`; entries are reassigned
        // to GRAY/BLACK during DFS, broadening the value type to int.
        $colour = \array_fill_keys($vertices, self::WHITE);

        foreach ($vertices as $vertex) {
            if ($colour[$vertex] !== self::WHITE) {
                continue;
            }
            $cycle = $this->visit($vertex, $adjacency, $colour, []);
            if ($cycle !== []) {
                return $cycle;
            }
        }

        return [];
    }

    /**
     * DFS visitor. Returns the cycle path (closed loop) when one is found,
     * otherwise `[]`.
     *
     * @param array<string, list<string>> $adjacency
     * @param array<string, int> $colour Mutated by reference across the recursion.
     * @param list<string> $stack Current recursion stack (used to extract cycle paths).
     *
     * @return list<string>
     */
    private function visit(string $node, array $adjacency, array &$colour, array $stack): array
    {
        $colour[$node] = self::GRAY;
        $stack[] = $node;

        $neighbours = $adjacency[$node] ?? [];
        // Sort so cycle reporting is deterministic.
        \sort($neighbours, \SORT_STRING);

        foreach ($neighbours as $neighbour) {
            // Unknown vertex — registry should have caught this; treat as
            // already-explored to keep traversal robust.
            if (!\array_key_exists($neighbour, $colour)) {
                continue;
            }

            if ($colour[$neighbour] === self::GRAY) {
                // Found a cycle. Walk the stack back to $neighbour and append
                // the closing repeat so callers see e.g. ['a', 'b', 'a'].
                $loop = [];
                $started = false;
                foreach ($stack as $entry) {
                    if (!$started && $entry !== $neighbour) {
                        continue;
                    }
                    $started = true;
                    $loop[] = $entry;
                }
                $loop[] = $neighbour;
                return $loop;
            }

            if ($colour[$neighbour] === self::WHITE) {
                $cycle = $this->visit($neighbour, $adjacency, $colour, $stack);
                if ($cycle !== []) {
                    return $cycle;
                }
            }
        }

        $colour[$node] = self::BLACK;
        return [];
    }
}

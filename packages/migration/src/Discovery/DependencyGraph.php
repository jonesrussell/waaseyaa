<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Discovery;

use Waaseyaa\Migration\MigrationDefinition;

/**
 * Read-only DAG over registered migrations (FR-015).
 *
 * The graph models *id-level* dependency edges: each node is a migration id,
 * each directed edge `a -> b` means "a depends on b" (b must run before a).
 *
 * Topological order uses Kahn's algorithm with deterministic tie-breaking by
 * lexicographic id so re-runs are stable across hosts. Cycle detection is the
 * concern of {@see CycleDetector} (separate class so callers can choose to
 * detect-and-report vs build-and-throw).
 *
 * Vertices that name a non-existent dependency are accepted at construction
 * time — {@see MigrationRegistry} validates missing-dependency cases via
 * {@see \Waaseyaa\Migration\Exception\MigrationDependencyMissingException}
 * before constructing the graph, so by the time a graph exists every named
 * edge has a registered target.
 *
 * @api
 */
final class DependencyGraph
{
    /** @var array<string, list<string>> */
    private readonly array $adjacency;

    /** @var list<string> */
    private readonly array $vertices;

    /**
     * @param array<string, list<string>> $adjacency Map of migration id -> list of dependency ids.
     */
    private function __construct(array $adjacency)
    {
        $this->adjacency = $adjacency;
        $vertices = \array_keys($adjacency);
        \sort($vertices, \SORT_STRING);
        $this->vertices = $vertices;
    }

    /**
     * Build a graph from an iterable of {@see MigrationDefinition} instances.
     *
     * Each definition contributes one vertex and zero-or-more outbound edges
     * (its `dependencies[]`). Order of iteration does not affect the result —
     * topological output uses lexicographic tie-breaking.
     *
     * @param iterable<MigrationDefinition> $definitions
     */
    public static function fromDefinitions(iterable $definitions): self
    {
        /** @var array<string, list<string>> $adjacency */
        $adjacency = [];

        foreach ($definitions as $definition) {
            $adjacency[$definition->id] = $definition->dependencies;
        }

        return new self($adjacency);
    }

    /**
     * Migration ids in dependency order — every dependency appears before its
     * dependents. Vertices with no incoming edges are emitted in lexicographic
     * order to break ties deterministically (Q3 resolution).
     *
     * **Precondition**: the graph is acyclic. Callers MUST run
     * {@see CycleDetector::detect()} first if any cycle is possible.
     *
     * @return list<string>
     *
     * @throws \LogicException When the graph is not acyclic (defensive guard;
     *                         {@see MigrationRegistry} catches cycles earlier).
     */
    public function topologicalOrder(): array
    {
        $inDegree = \array_fill_keys($this->vertices, 0);
        foreach ($this->adjacency as $node => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (!\array_key_exists($dependency, $inDegree)) {
                    // Defensive: registry validates this earlier, but be explicit.
                    $inDegree[$dependency] = 0;
                }
                // Edge: dependency -> node (dependency must run first); increase
                // the in-degree of the dependent.
                ++$inDegree[$node];
            }
        }

        // Reverse adjacency: dependency -> [dependents]
        $reverse = \array_fill_keys(\array_keys($inDegree), []);
        foreach ($this->adjacency as $node => $dependencies) {
            foreach ($dependencies as $dependency) {
                $reverse[$dependency][] = $node;
            }
        }
        foreach ($reverse as &$dependents) {
            \sort($dependents, \SORT_STRING);
        }
        unset($dependents);

        // Seed Kahn's queue with every zero-in-degree vertex, sorted.
        $ready = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $ready[] = $node;
            }
        }
        \sort($ready, \SORT_STRING);

        $order = [];
        while ($ready !== []) {
            $node = \array_shift($ready);
            $order[] = $node;

            foreach ($reverse[$node] as $dependent) {
                --$inDegree[$dependent];
                if ($inDegree[$dependent] === 0) {
                    // Insert lexicographically to keep determinism.
                    $ready[] = $dependent;
                    \sort($ready, \SORT_STRING);
                }
            }
        }

        if (\count($order) !== \count($inDegree)) {
            throw new \LogicException(
                'DependencyGraph::topologicalOrder() called on a graph that contains a cycle; '
                . 'run CycleDetector::detect() before requesting a topological order.',
            );
        }

        return $order;
    }

    /**
     * Outbound edges from `$id` — the migrations `$id` declares as
     * prerequisites. Returns `[]` for leaf migrations.
     *
     * @return list<string>
     *
     * @throws \OutOfBoundsException When `$id` is not a registered vertex.
     */
    public function dependencies(string $id): array
    {
        if (!\array_key_exists($id, $this->adjacency)) {
            throw new \OutOfBoundsException(\sprintf(
                'DependencyGraph: migration %s is not registered.',
                \var_export($id, true),
            ));
        }
        return $this->adjacency[$id];
    }

    /**
     * Reverse edges into `$id` — every migration that names `$id` in its
     * `dependencies[]`. Used by status displays and `import:rollback` to walk
     * the cascade.
     *
     * @return list<string>
     *
     * @throws \OutOfBoundsException When `$id` is not a registered vertex.
     */
    public function dependents(string $id): array
    {
        if (!\array_key_exists($id, $this->adjacency)) {
            throw new \OutOfBoundsException(\sprintf(
                'DependencyGraph: migration %s is not registered.',
                \var_export($id, true),
            ));
        }
        $dependents = [];
        foreach ($this->adjacency as $node => $dependencies) {
            if (\in_array($id, $dependencies, true)) {
                $dependents[] = $node;
            }
        }
        \sort($dependents, \SORT_STRING);
        return $dependents;
    }

    /**
     * Every registered vertex, lexicographically sorted.
     *
     * @return list<string>
     */
    public function vertices(): array
    {
        return $this->vertices;
    }

    /**
     * Raw adjacency map (id -> dependency ids). Exposed for {@see CycleDetector}.
     *
     * @return array<string, list<string>>
     */
    public function adjacency(): array
    {
        return $this->adjacency;
    }
}

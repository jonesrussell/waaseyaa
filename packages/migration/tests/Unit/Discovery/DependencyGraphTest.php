<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Discovery\CycleDetector;
use Waaseyaa\Migration\Discovery\DependencyGraph;
use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\PluginFixtures\PluginFixturesTrait;

#[CoversClass(DependencyGraph::class)]
#[CoversClass(CycleDetector::class)]
final class DependencyGraphTest extends TestCase
{
    use PluginFixturesTrait;

    #[Test]
    public function topological_order_is_acyclic_diamond(): void
    {
        // diamond:  d -> b -> a
        //           d -> c -> a
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('a'),
            $this->definition('b', ['a']),
            $this->definition('c', ['a']),
            $this->definition('d', ['b', 'c']),
        ]);

        self::assertSame([], (new CycleDetector())->detect($graph));
        self::assertSame(['a', 'b', 'c', 'd'], $graph->topologicalOrder());
    }

    #[Test]
    public function topological_order_breaks_ties_lexicographically(): void
    {
        // Three independent leaves — order is purely lexicographic.
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('charlie'),
            $this->definition('alpha'),
            $this->definition('bravo'),
        ]);

        self::assertSame(['alpha', 'bravo', 'charlie'], $graph->topologicalOrder());
    }

    #[Test]
    public function dependencies_returns_declared_outbound_edges(): void
    {
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('a'),
            $this->definition('b'),
            $this->definition('c', ['a', 'b']),
        ]);

        self::assertSame(['a', 'b'], $graph->dependencies('c'));
        self::assertSame([], $graph->dependencies('a'));
    }

    #[Test]
    public function dependents_returns_reverse_edges_sorted(): void
    {
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('a'),
            $this->definition('zebra', ['a']),
            $this->definition('alpha', ['a']),
        ]);

        self::assertSame(['alpha', 'zebra'], $graph->dependents('a'));
        self::assertSame([], $graph->dependents('alpha'));
    }

    #[Test]
    public function vertices_returns_sorted_ids(): void
    {
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('zulu'),
            $this->definition('alpha'),
            $this->definition('mike'),
        ]);

        self::assertSame(['alpha', 'mike', 'zulu'], $graph->vertices());
    }

    #[Test]
    public function dependencies_raises_on_unknown_id(): void
    {
        $graph = DependencyGraph::fromDefinitions([$this->definition('a')]);

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage("'unknown' is not registered");

        $graph->dependencies('unknown');
    }

    #[Test]
    public function dependents_raises_on_unknown_id(): void
    {
        $graph = DependencyGraph::fromDefinitions([$this->definition('a')]);

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage("'unknown' is not registered");

        $graph->dependents('unknown');
    }

    #[Test]
    public function cycle_of_length_two_is_detected_with_full_path(): void
    {
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('a', ['b']),
            $this->definition('b', ['a']),
        ]);

        self::assertSame(['a', 'b', 'a'], (new CycleDetector())->detect($graph));
    }

    #[Test]
    public function cycle_of_length_three_is_detected_with_full_path(): void
    {
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('a', ['b']),
            $this->definition('b', ['c']),
            $this->definition('c', ['a']),
        ]);

        self::assertSame(['a', 'b', 'c', 'a'], (new CycleDetector())->detect($graph));
    }

    #[Test]
    public function topological_order_on_cyclic_graph_raises_defensive_guard(): void
    {
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('a', ['b']),
            $this->definition('b', ['a']),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('contains a cycle');

        $graph->topologicalOrder();
    }

    #[Test]
    public function acyclic_graph_has_no_cycle(): void
    {
        $graph = DependencyGraph::fromDefinitions([
            $this->definition('a'),
            $this->definition('b', ['a']),
            $this->definition('c', ['b']),
        ]);

        self::assertSame([], (new CycleDetector())->detect($graph));
        self::assertSame(['a', 'b', 'c'], $graph->topologicalOrder());
    }

    /**
     * @param list<string> $dependencies
     */
    private function definition(string $id, array $dependencies = []): MigrationDefinition
    {
        return new MigrationDefinition(
            id: $id,
            source: $this->fixtureSource('wp_post'),
            process: ['title' => 'post_title'],
            destination: $this->fixtureDestination('node_destination'),
            dependencies: $dependencies,
        );
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Dependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Dependency\DependencyGraph;

#[CoversClass(DependencyGraph::class)]
final class DependencyGraphTest extends TestCase
{
    #[Test]
    public function emptyGraphIsValid(): void
    {
        $graph = new DependencyGraph(edges: [], topologicalOrder: []);

        self::assertSame([], $graph->nodes());
        self::assertFalse($graph->hasNode('role.admin'));
        self::assertSame([], $graph->edgesFrom('role.admin'));
        self::assertTrue($graph->isAcyclic());
    }

    #[Test]
    public function singleNodeGraphReportsItself(): void
    {
        $graph = new DependencyGraph(
            edges: ['role.admin' => []],
            topologicalOrder: ['role.admin'],
        );

        self::assertSame(['role.admin'], $graph->nodes());
        self::assertTrue($graph->hasNode('role.admin'));
        self::assertSame([], $graph->edgesFrom('role.admin'));
    }

    #[Test]
    public function edgesFromReturnsOutgoingDependents(): void
    {
        // role.admin -> permission.x (role.admin is the dependency)
        $graph = new DependencyGraph(
            edges: [
                'role.admin' => ['permission.x'],
                'permission.x' => [],
            ],
            topologicalOrder: ['role.admin', 'permission.x'],
        );

        self::assertSame(['permission.x'], $graph->edgesFrom('role.admin'));
        self::assertSame([], $graph->edgesFrom('permission.x'));
        self::assertTrue($graph->hasNode('permission.x'));
    }

    #[Test]
    public function nodesReturnsTopologicalOrder(): void
    {
        $graph = new DependencyGraph(
            edges: [
                'a.a' => ['b.b'],
                'b.b' => ['c.c'],
                'c.c' => [],
            ],
            topologicalOrder: ['a.a', 'b.b', 'c.c'],
        );

        self::assertSame(['a.a', 'b.b', 'c.c'], $graph->nodes());
    }

    #[Test]
    public function isAcyclicAlwaysTrueByConstruction(): void
    {
        $graph = new DependencyGraph(edges: ['x.y' => []], topologicalOrder: ['x.y']);
        self::assertTrue($graph->isAcyclic());
    }

    #[Test]
    public function constructorRejectsDanglingEdgeTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('references a node not present in the edges map');

        new DependencyGraph(
            edges: ['a.a' => ['ghost.ghost']],
            topologicalOrder: ['a.a'],
        );
    }

    #[Test]
    public function constructorRejectsOrderSizeMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('topological order has');

        new DependencyGraph(
            edges: ['a.a' => [], 'b.b' => []],
            topologicalOrder: ['a.a'],
        );
    }

    #[Test]
    public function constructorRejectsDuplicateInOrder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicates');

        new DependencyGraph(
            edges: ['a.a' => [], 'b.b' => []],
            topologicalOrder: ['a.a', 'a.a'],
        );
    }

    #[Test]
    public function constructorRejectsMissingNodeInOrder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing node');

        new DependencyGraph(
            edges: ['a.a' => [], 'b.b' => []],
            topologicalOrder: ['a.a', 'c.c'],
        );
    }

    #[Test]
    public function constructorRejectsOrderInconsistentWithEdges(): void
    {
        // Edge a.a -> b.b requires position(a.a) < position(b.b).
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('inconsistent with edges');

        new DependencyGraph(
            edges: [
                'a.a' => ['b.b'],
                'b.b' => [],
            ],
            topologicalOrder: ['b.b', 'a.a'],
        );
    }
}

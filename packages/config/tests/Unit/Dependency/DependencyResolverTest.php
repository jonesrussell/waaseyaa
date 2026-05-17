<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Dependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Dependency\DependencyGraph;
use Waaseyaa\Config\Dependency\DependencyResolver;
use Waaseyaa\Config\Dependency\Exception\ConfigDependencyCycleException;
use Waaseyaa\Config\Dependency\Exception\ConfigDependencyMissingException;

#[CoversClass(DependencyResolver::class)]
final class DependencyResolverTest extends TestCase
{
    #[Test]
    public function emptyInputProducesEmptyGraph(): void
    {
        $graph = (new DependencyResolver())->resolve([]);

        self::assertInstanceOf(DependencyGraph::class, $graph);
        self::assertSame([], $graph->nodes());
        self::assertTrue($graph->isAcyclic());
    }

    #[Test]
    public function singleIsolatedNodeProducesSingleEntryOrder(): void
    {
        $graph = (new DependencyResolver())->resolve(['role.admin' => []]);

        self::assertSame(['role.admin'], $graph->nodes());
        self::assertSame([], $graph->edgesFrom('role.admin'));
    }

    #[Test]
    public function linearChainOrdersDependenciesFirst(): void
    {
        // C depends on B; B depends on A. Import order: A, B, C.
        $graph = (new DependencyResolver())->resolve([
            'c.c' => ['b.b'],
            'b.b' => ['a.a'],
            'a.a' => [],
        ]);

        self::assertSame(['a.a', 'b.b', 'c.c'], $graph->nodes());
    }

    #[Test]
    public function diamondTieBreaksLexicographically(): void
    {
        // D depends on B and C; B and C both depend on A. Expected: A, B, C, D.
        // B and C share topological level; lex order picks B before C.
        $graph = (new DependencyResolver())->resolve([
            'd.d' => ['b.b', 'c.c'],
            'b.b' => ['a.a'],
            'c.c' => ['a.a'],
            'a.a' => [],
        ]);

        self::assertSame(['a.a', 'b.b', 'c.c', 'd.d'], $graph->nodes());
    }

    #[Test]
    public function shortCycleRaisesCycleException(): void
    {
        try {
            (new DependencyResolver())->resolve([
                'a.a' => ['b.b'],
                'b.b' => ['a.a'],
            ]);
            self::fail('Expected ConfigDependencyCycleException.');
        } catch (ConfigDependencyCycleException $e) {
            $cycle = $e->getCycle();
            // Closed cycle: first == last; length >= 3.
            self::assertGreaterThanOrEqual(3, \count($cycle));
            self::assertSame($cycle[0], $cycle[\count($cycle) - 1]);
            // Both nodes must appear in the cycle.
            self::assertContains('a.a', $cycle);
            self::assertContains('b.b', $cycle);
            self::assertSame('config.dependency.cycle', $e->errorCode);
        }
    }

    #[Test]
    public function longCycleCarriesFullPath(): void
    {
        // A -> B -> C -> D -> A
        try {
            (new DependencyResolver())->resolve([
                'a.a' => ['d.d'],
                'b.b' => ['a.a'],
                'c.c' => ['b.b'],
                'd.d' => ['c.c'],
            ]);
            self::fail('Expected ConfigDependencyCycleException.');
        } catch (ConfigDependencyCycleException $e) {
            $cycle = $e->getCycle();
            // 4 nodes + closing repeat = 5 entries.
            self::assertCount(5, $cycle);
            self::assertSame($cycle[0], $cycle[4]);
            foreach (['a.a', 'b.b', 'c.c', 'd.d'] as $expected) {
                self::assertContains($expected, $cycle);
            }
        }
    }

    #[Test]
    public function missingDependencyRaisesWithBothRefs(): void
    {
        try {
            (new DependencyResolver())->resolve([
                'menu.main' => ['taxonomy_vocabulary.ghost'],
            ]);
            self::fail('Expected ConfigDependencyMissingException.');
        } catch (ConfigDependencyMissingException $e) {
            self::assertSame('taxonomy_vocabulary.ghost', $e->missingRef);
            self::assertSame('menu.main', $e->requiredBy);
            self::assertSame('config.dependency.missing', $e->errorCode);
        }
    }

    #[Test]
    public function dependencySatisfiedByActiveStoreIsAccepted(): void
    {
        // menu.main depends on taxonomy_vocabulary.cats, which is NOT in
        // declarations but IS present in the active store.
        $graph = (new DependencyResolver())->resolve(
            declarations: ['menu.main' => ['taxonomy_vocabulary.cats']],
            activeRefs: ['taxonomy_vocabulary.cats'],
        );

        // Active-store ref is not added as a graph node — only the sync-file ref is ordered.
        self::assertSame(['menu.main'], $graph->nodes());
        self::assertFalse($graph->hasNode('taxonomy_vocabulary.cats'));
    }

    #[Test]
    public function duplicateDependencyDeclarationsAreDeduped(): void
    {
        $graph = (new DependencyResolver())->resolve([
            'b.b' => ['a.a', 'a.a', 'a.a'],
            'a.a' => [],
        ]);

        self::assertSame(['a.a', 'b.b'], $graph->nodes());
        self::assertSame(['b.b'], $graph->edgesFrom('a.a'));
    }

    #[Test]
    public function noDependencyCheckBypassSemanticsAreOutOfScope(): void
    {
        // The resolver always runs cycle + missing detection. The
        // `--no-dependency-check` flag (FR-007) is enforced at the
        // ConfigImporter layer (WP04) which skips invoking the resolver
        // entirely. This test documents that semantics: calling resolve()
        // with a cyclic input always raises — the bypass is *not* a flag
        // on the resolver itself.
        $this->expectException(ConfigDependencyCycleException::class);

        (new DependencyResolver())->resolve([
            'a.a' => ['b.b'],
            'b.b' => ['a.a'],
        ]);
    }

    #[Test]
    public function deterministicOrderingAcrossDeclarationInputOrder(): void
    {
        // Two semantically-identical inputs given in different declaration
        // orders must produce the same topological order.
        $resolver = new DependencyResolver();

        $graphA = $resolver->resolve([
            'd.d' => ['b.b', 'c.c'],
            'a.a' => [],
            'b.b' => ['a.a'],
            'c.c' => ['a.a'],
        ]);

        $graphB = $resolver->resolve([
            'a.a' => [],
            'b.b' => ['a.a'],
            'c.c' => ['a.a'],
            'd.d' => ['b.b', 'c.c'],
        ]);

        self::assertSame($graphA->nodes(), $graphB->nodes());
        self::assertSame(['a.a', 'b.b', 'c.c', 'd.d'], $graphA->nodes());
    }
}

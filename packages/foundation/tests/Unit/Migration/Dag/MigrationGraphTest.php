<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Migration\Dag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\Dag\MigrationCycleDetectedException;
use Waaseyaa\Foundation\Migration\Dag\MigrationGraph;
use Waaseyaa\Foundation\Migration\Dag\MigrationNode;
use Waaseyaa\Foundation\Migration\Dag\UnknownDependencyException;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

#[CoversClass(MigrationGraph::class)]
#[CoversClass(MigrationCycleDetectedException::class)]
#[CoversClass(UnknownDependencyException::class)]
final class MigrationGraphTest extends TestCase
{
    #[Test]
    public function emptyInputProducesEmptyOrder(): void
    {
        $graph = MigrationGraph::build([]);

        self::assertSame([], $graph->topologicalOrder());
    }

    #[Test]
    public function tieBreakSortsByPackageThenIdAscending(): void
    {
        // Two nodes, no dependencies. Tie-break (package ASC, id ASC).
        $a = self::legacyNode('waaseyaa/users:001_init', 'waaseyaa/users');
        $b = self::legacyNode('waaseyaa/groups:001_init', 'waaseyaa/groups');

        $order = MigrationGraph::build([$a, $b])->topologicalOrder();

        self::assertSame(['waaseyaa/groups:001_init', 'waaseyaa/users:001_init'], self::ids($order));
    }

    #[Test]
    public function tieBreakSortsByIdWithinPackage(): void
    {
        $first = self::legacyNode('waaseyaa/users:001_init', 'waaseyaa/users');
        $second = self::legacyNode('waaseyaa/users:002_add_email', 'waaseyaa/users');

        $order = MigrationGraph::build([$second, $first])->topologicalOrder();

        self::assertSame(
            ['waaseyaa/users:001_init', 'waaseyaa/users:002_add_email'],
            self::ids($order),
        );
    }

    #[Test]
    public function legacyAfterPackageProducesEdgesToEveryNodeInThatPackage(): void
    {
        $base = self::legacyNode('waaseyaa/base:001_init', 'waaseyaa/base');
        $dependent = self::legacyNode(
            'waaseyaa/dependent:001_init',
            'waaseyaa/dependent',
            after: ['waaseyaa/base'],
        );

        $order = MigrationGraph::build([$dependent, $base])->topologicalOrder();

        self::assertSame(
            ['waaseyaa/base:001_init', 'waaseyaa/dependent:001_init'],
            self::ids($order),
        );
    }

    #[Test]
    public function v2DependencyOnLegacyMigrationIdResolvesAcrossKinds(): void
    {
        $legacy = self::legacyNode('waaseyaa/base:001_init', 'waaseyaa/base');
        $v2 = self::v2Node(
            id: 'waaseyaa/groups:v2:add-archived-flag',
            package: 'waaseyaa/groups',
            deps: ['waaseyaa/base:001_init'],
        );

        $order = MigrationGraph::build([$v2, $legacy])->topologicalOrder();

        self::assertSame(
            ['waaseyaa/base:001_init', 'waaseyaa/groups:v2:add-archived-flag'],
            self::ids($order),
        );
    }

    #[Test]
    public function legacyDependencyOnV2MigrationIdResolvesAcrossKinds(): void
    {
        $v2 = self::v2Node(
            id: 'waaseyaa/groups:v2:add-archived-flag',
            package: 'waaseyaa/groups',
            deps: [],
        );
        $legacy = self::legacyNode(
            'waaseyaa/billing:001_init',
            'waaseyaa/billing',
            after: ['waaseyaa/groups:v2:add-archived-flag'],
        );

        $order = MigrationGraph::build([$legacy, $v2])->topologicalOrder();

        self::assertSame(
            ['waaseyaa/groups:v2:add-archived-flag', 'waaseyaa/billing:001_init'],
            self::ids($order),
        );
    }

    #[Test]
    public function v2UnknownDependencyThrowsStableCode(): void
    {
        $v2 = self::v2Node(
            id: 'waaseyaa/groups:v2:add-archived-flag',
            package: 'waaseyaa/groups',
            deps: ['waaseyaa/nonexistent'],
        );

        try {
            MigrationGraph::build([$v2]);
            self::fail('Expected UnknownDependencyException for v2 unknown reference.');
        } catch (UnknownDependencyException $e) {
            self::assertSame('UNKNOWN_DEPENDENCY', $e->diagnosticCode());
            self::assertSame('waaseyaa/nonexistent', $e->dependency);
            self::assertSame('waaseyaa/groups:v2:add-archived-flag', $e->sourceId);
        }
    }

    #[Test]
    public function legacyUnknownAfterIsSilentlyDropped(): void
    {
        // Pre-WP06 behaviour: legacy $after entries that don't resolve
        // are silently ignored. The graph still orders the node.
        $legacy = self::legacyNode(
            'waaseyaa/test:001_init',
            'waaseyaa/test',
            after: ['waaseyaa/nonexistent'],
        );

        $order = MigrationGraph::build([$legacy])->topologicalOrder();

        self::assertSame(['waaseyaa/test:001_init'], self::ids($order));
    }

    #[Test]
    public function cycleAcrossKindsIsDetectedWithStableCode(): void
    {
        // legacy depends on v2 depends on legacy
        $legacy = self::legacyNode(
            'a/x:001_init',
            'a/x',
            after: ['a/y:v2:foo'],
        );
        $v2 = self::v2Node(
            id: 'a/y:v2:foo',
            package: 'a/y',
            deps: ['a/x:001_init'],
        );

        try {
            MigrationGraph::build([$legacy, $v2])->topologicalOrder();
            self::fail('Expected MigrationCycleDetectedException.');
        } catch (MigrationCycleDetectedException $e) {
            self::assertSame('MIGRATION_CYCLE', $e->diagnosticCode());
            self::assertNotEmpty($e->cycle);
            // The cycle includes both nodes (in some rotation).
            $ids = $e->cycle;
            self::assertContains('a/x:001_init', $ids);
            self::assertContains('a/y:v2:foo', $ids);
        }
    }

    #[Test]
    public function duplicateIdsRejectedAtBuildTime(): void
    {
        $a = self::legacyNode('waaseyaa/test:001_init', 'waaseyaa/test');
        $b = self::legacyNode('waaseyaa/test:001_init', 'waaseyaa/test');

        $this->expectException(\InvalidArgumentException::class);

        MigrationGraph::build([$a, $b]);
    }

    #[Test]
    public function selfDependencyIsIgnoredNotCycle(): void
    {
        $a = self::legacyNode(
            'waaseyaa/test:001_init',
            'waaseyaa/test',
            after: ['waaseyaa/test:001_init'],
        );

        // A self-reference that resolves to the node id itself is dropped
        // (the graph doesn't add an edge that would always cycle).
        $order = MigrationGraph::build([$a])->topologicalOrder();

        self::assertSame(['waaseyaa/test:001_init'], self::ids($order));
    }

    #[Test]
    public function packageDepEdgeFromV2ConnectsToEveryNodeInThatPackage(): void
    {
        $baseA = self::legacyNode('waaseyaa/base:001_init', 'waaseyaa/base');
        $baseB = self::legacyNode('waaseyaa/base:002_add_x', 'waaseyaa/base');
        $v2 = self::v2Node(
            id: 'waaseyaa/groups:v2:foo',
            package: 'waaseyaa/groups',
            deps: ['waaseyaa/base'],
        );

        $order = MigrationGraph::build([$v2, $baseA, $baseB])->topologicalOrder();

        // Both base nodes must appear before the v2 node.
        $orderIds = self::ids($order);
        self::assertSame(2, array_search('waaseyaa/groups:v2:foo', $orderIds, true));
    }

    /** @param list<string> $after */
    private static function legacyNode(string $id, string $package, array $after = []): MigrationNode
    {
        $migration = new class ($after) extends Migration {
            /** @param list<string> $after */
            public function __construct(array $after)
            {
                $this->after = $after;
            }
            public function up(SchemaBuilder $schema): void {}
        };

        return MigrationNode::fromLegacy($id, $package, $migration);
    }

    /** @param list<string> $deps */
    private static function v2Node(string $id, string $package, array $deps): MigrationNode
    {
        $v2 = new class ($id, $package, $deps) implements MigrationInterfaceV2 {
            /** @param list<string> $deps */
            public function __construct(
                private readonly string $id,
                private readonly string $package,
                private readonly array $deps,
            ) {}

            public function migrationId(): string
            {
                return $this->id;
            }
            public function package(): string
            {
                return $this->package;
            }
            public function dependencies(): array
            {
                return $this->deps;
            }
            public function plan(): MigrationPlan
            {
                return new MigrationPlan(
                    migrationId: $this->id,
                    package: $this->package,
                    dependencies: $this->deps,
                    root: CompositeDiff::empty(),
                );
            }
        };

        return MigrationNode::fromV2($v2);
    }

    /**
     * @param list<MigrationNode> $nodes
     * @return list<string>
     */
    private static function ids(array $nodes): array
    {
        return array_map(static fn(MigrationNode $n): string => $n->id, $nodes);
    }
}

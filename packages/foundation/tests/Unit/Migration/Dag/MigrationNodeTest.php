<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Migration\Dag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\Dag\MigrationKind;
use Waaseyaa\Foundation\Migration\Dag\MigrationNode;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

#[CoversClass(MigrationNode::class)]
final class MigrationNodeTest extends TestCase
{
    #[Test]
    public function fromLegacyExtractsAfterAsDependencies(): void
    {
        $migration = new class extends Migration {
            public array $after = ['waaseyaa/base', 'waaseyaa/users'];
            public function up(SchemaBuilder $schema): void {}
        };

        $node = MigrationNode::fromLegacy('waaseyaa/test:001_init', 'waaseyaa/test', $migration);

        self::assertSame('waaseyaa/test:001_init', $node->id);
        self::assertSame('waaseyaa/test', $node->package);
        self::assertSame(MigrationKind::Legacy, $node->kind);
        self::assertSame(['waaseyaa/base', 'waaseyaa/users'], $node->dependencies);
        self::assertSame($migration, $node->legacy);
        self::assertNull($node->v2);
    }

    #[Test]
    public function fromV2PullsIdentityFromTheInterface(): void
    {
        $v2 = new class implements MigrationInterfaceV2 {
            public function migrationId(): string
            {
                return 'waaseyaa/groups:v2:add-archived-flag';
            }
            public function package(): string
            {
                return 'waaseyaa/groups';
            }
            public function dependencies(): array
            {
                return ['waaseyaa/base'];
            }
            public function plan(): MigrationPlan
            {
                return new MigrationPlan(
                    migrationId: $this->migrationId(),
                    package: $this->package(),
                    dependencies: $this->dependencies(),
                    root: CompositeDiff::empty(),
                );
            }
        };

        $node = MigrationNode::fromV2($v2);

        self::assertSame('waaseyaa/groups:v2:add-archived-flag', $node->id);
        self::assertSame('waaseyaa/groups', $node->package);
        self::assertSame(MigrationKind::V2, $node->kind);
        self::assertSame(['waaseyaa/base'], $node->dependencies);
        self::assertNull($node->legacy);
        self::assertSame($v2, $node->v2);
    }

    #[Test]
    public function constructingWithBothSourcesIsRejected(): void
    {
        $legacy = new class extends Migration {
            public function up(SchemaBuilder $schema): void {}
        };
        $v2 = new class implements MigrationInterfaceV2 {
            public function migrationId(): string
            {
                return 'a/b:v2:c';
            }
            public function package(): string
            {
                return 'a/b';
            }
            public function dependencies(): array
            {
                return [];
            }
            public function plan(): MigrationPlan
            {
                return new MigrationPlan(
                    migrationId: 'a/b:v2:c',
                    package: 'a/b',
                    dependencies: [],
                    root: CompositeDiff::empty(),
                );
            }
        };

        $this->expectException(\InvalidArgumentException::class);

        new MigrationNode(
            id: 'a/b:v2:c',
            package: 'a/b',
            kind: MigrationKind::V2,
            dependencies: [],
            legacy: $legacy,
            v2: $v2,
        );
    }

    #[Test]
    public function constructingWithNeitherSourceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MigrationNode(
            id: 'a/b:v2:c',
            package: 'a/b',
            kind: MigrationKind::V2,
            dependencies: [],
        );
    }

    #[Test]
    public function kindMismatchWithSourceIsRejected(): void
    {
        $legacy = new class extends Migration {
            public function up(SchemaBuilder $schema): void {}
        };

        $this->expectException(\InvalidArgumentException::class);

        new MigrationNode(
            id: 'a/b:001_init',
            package: 'a/b',
            kind: MigrationKind::V2,
            dependencies: [],
            legacy: $legacy,
        );
    }
}

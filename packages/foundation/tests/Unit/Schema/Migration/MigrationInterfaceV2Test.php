<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

#[CoversClass(MigrationPlan::class)]
final class MigrationInterfaceV2Test extends TestCase
{
    #[Test]
    public function fixtureImplementationSatisfiesContract(): void
    {
        $migration = new FixtureMigration();

        self::assertSame('waaseyaa/users:v2:add-email', $migration->migrationId());
        self::assertSame('waaseyaa/users', $migration->package());
        self::assertSame(['waaseyaa/users:v2:create-table'], $migration->dependencies());
        self::assertInstanceOf(MigrationPlan::class, $migration->plan());
        self::assertFalse($migration->plan()->isEmpty());
    }

    #[Test]
    public function planChecksumIsStableAcrossRepeatedCalls(): void
    {
        $migration = new FixtureMigration();

        self::assertSame($migration->plan()->checksum(), $migration->plan()->checksum());
    }

    #[Test]
    public function emptyPlanFixtureRecordsNoOpApply(): void
    {
        $migration = new EmptyFixtureMigration();

        self::assertTrue($migration->plan()->isEmpty());
        // Empty plans must still expose the ledger key so the Migrator
        // can record the apply (per §15 Q3 / WP06 risks).
        self::assertSame('waaseyaa/users:v2:noop-conditional', $migration->migrationId());
    }

    #[Test]
    public function isReadonlyInterfaceWithFinalImplementation(): void
    {
        $reflection = new \ReflectionClass(FixtureMigration::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->implementsInterface(MigrationInterfaceV2::class));
    }
}

/**
 * @internal Test fixture only.
 */
final readonly class FixtureMigration implements MigrationInterfaceV2
{
    public function migrationId(): string
    {
        return 'waaseyaa/users:v2:add-email';
    }

    public function package(): string
    {
        return 'waaseyaa/users';
    }

    public function dependencies(): array
    {
        return ['waaseyaa/users:v2:create-table'];
    }

    public function plan(): MigrationPlan
    {
        return new MigrationPlan(
            migrationId: $this->migrationId(),
            package: $this->package(),
            dependencies: $this->dependencies(),
            root: new CompositeDiff([
                new AddColumn('users', 'email', new ColumnSpec('varchar', false, null, 255)),
            ]),
        );
    }
}

/**
 * @internal Test fixture only — the conditional no-op variant.
 */
final readonly class EmptyFixtureMigration implements MigrationInterfaceV2
{
    public function migrationId(): string
    {
        return 'waaseyaa/users:v2:noop-conditional';
    }

    public function package(): string
    {
        return 'waaseyaa/users';
    }

    public function dependencies(): array
    {
        return [];
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
}

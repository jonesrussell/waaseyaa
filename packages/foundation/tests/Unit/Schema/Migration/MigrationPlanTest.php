<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

#[CoversClass(MigrationPlan::class)]
#[CoversClass(CompositeDiff::class)]
final class MigrationPlanTest extends TestCase
{
    private static function nonEmptyRoot(): CompositeDiff
    {
        return new CompositeDiff([
            new AddColumn('users', 'email', new ColumnSpec('varchar', false, null, 255)),
        ]);
    }

    #[Test]
    public function exposesConstructorArgs(): void
    {
        $root = self::nonEmptyRoot();
        $plan = new MigrationPlan(
            migrationId: 'waaseyaa/users:v2:add-email',
            package: 'waaseyaa/users',
            dependencies: ['waaseyaa/users:v2:create-table'],
            root: $root,
        );

        self::assertSame('waaseyaa/users:v2:add-email', $plan->migrationId);
        self::assertSame('waaseyaa/users', $plan->package);
        self::assertSame(['waaseyaa/users:v2:create-table'], $plan->dependencies);
        self::assertSame($root, $plan->root);
    }

    #[Test]
    public function nonEmptyRootRoundTripsThroughCanonicalAndChecksum(): void
    {
        $root = self::nonEmptyRoot();
        $plan = new MigrationPlan('vendor/pkg:v2:slug', 'vendor/pkg', [], $root);

        self::assertSame($root->toCanonical(), $plan->toCanonical());
        self::assertSame($root->checksum(), $plan->checksum());
    }

    #[Test]
    public function emptyPlanIsEmpty(): void
    {
        $plan = new MigrationPlan(
            migrationId: 'vendor/pkg:v2:no-op',
            package: 'vendor/pkg',
            dependencies: [],
            root: CompositeDiff::empty(),
        );

        self::assertTrue($plan->isEmpty());
        self::assertTrue($plan->root->isEmpty());
    }

    #[Test]
    public function nonEmptyPlanIsNotEmpty(): void
    {
        $plan = new MigrationPlan('vendor/pkg:v2:slug', 'vendor/pkg', [], self::nonEmptyRoot());

        self::assertFalse($plan->isEmpty());
    }

    #[Test]
    public function metadataIsExcludedFromChecksumIdentity(): void
    {
        $root = self::nonEmptyRoot();

        $a = new MigrationPlan('vendor-a/pkg:v2:slug', 'vendor-a/pkg', ['some/dep:v2:x'], $root);
        $b = new MigrationPlan('vendor-b/different:v2:other', 'vendor-b/different', [], $root);

        self::assertSame(
            $a->checksum(),
            $b->checksum(),
            'Metadata is not part of structural identity per §15 Q3 — only the root composite hashes.',
        );
        self::assertSame($a->toCanonical(), $b->toCanonical());
    }

    #[Test]
    public function differentRootsProduceDifferentChecksums(): void
    {
        $a = new MigrationPlan('vendor/pkg:v2:a', 'vendor/pkg', [], self::nonEmptyRoot());
        $b = new MigrationPlan('vendor/pkg:v2:a', 'vendor/pkg', [], CompositeDiff::empty());

        self::assertNotSame($a->checksum(), $b->checksum());
    }

    #[Test]
    public function diffHashIsNullByDefault(): void
    {
        $plan = new MigrationPlan('vendor/pkg:v2:slug', 'vendor/pkg', [], self::nonEmptyRoot());

        self::assertNull(
            $plan->diffHash(),
            'diffHash() is computed by the compiled-plan stage (WP04), not by the value type.',
        );
        self::assertNull($plan->compiledDiffHash);
    }

    #[Test]
    public function compiledDiffHashRoundTripsForwardCompatSeam(): void
    {
        // WP04 will construct plans with the compiled-plan hash populated.
        // Pre-validating the forward-compat seam here keeps the contract
        // clear and stops PHPStan from inferring `?string` is unreachable.
        $hash = str_repeat('a', 64);
        $plan = new MigrationPlan(
            'vendor/pkg:v2:slug',
            'vendor/pkg',
            [],
            self::nonEmptyRoot(),
            $hash,
        );

        self::assertSame($hash, $plan->diffHash());
        self::assertSame($hash, $plan->compiledDiffHash);
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(MigrationPlan::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

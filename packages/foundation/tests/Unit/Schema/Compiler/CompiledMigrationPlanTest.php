<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\CompiledMigrationPlan;
use Waaseyaa\Foundation\Schema\Compiler\Step\AlterTableAddColumn;
use Waaseyaa\Foundation\Schema\Compiler\Step\ExecuteStatement;

#[CoversClass(CompiledMigrationPlan::class)]
final class CompiledMigrationPlanTest extends TestCase
{
    #[Test]
    public function emptyPlanReportsItself(): void
    {
        $plan = new CompiledMigrationPlan([]);

        self::assertTrue($plan->isEmpty());
        self::assertSame('{"steps":[]}', $plan->toCanonicalJson());
    }

    #[Test]
    public function nonEmptyPlanIsNotEmpty(): void
    {
        $plan = new CompiledMigrationPlan([new ExecuteStatement('SELECT 1')]);

        self::assertFalse($plan->isEmpty());
    }

    #[Test]
    public function canonicalShapeWrapsStepsKey(): void
    {
        $plan = new CompiledMigrationPlan([
            new AlterTableAddColumn('users', 'email', 'ALTER TABLE "users" ADD COLUMN "email" TEXT'),
        ]);

        self::assertSame(
            [
                'steps' => [
                    [
                        'column' => 'email',
                        'kind' => 'alter_table_add_column',
                        'sql' => 'ALTER TABLE "users" ADD COLUMN "email" TEXT',
                        'table' => 'users',
                    ],
                ],
            ],
            $plan->toCanonical(),
        );
    }

    #[Test]
    public function diffHashIsByteStableForLockedFixture(): void
    {
        $plan = new CompiledMigrationPlan([
            new ExecuteStatement('ALTER TABLE "users" RENAME TO "members"'),
        ]);

        $expectedJson = '{"steps":[{"kind":"execute_statement","sql":"ALTER TABLE \"users\" RENAME TO \"members\""}]}';
        self::assertSame($expectedJson, $plan->toCanonicalJson());
        self::assertSame(hash('sha256', $expectedJson), $plan->diffHash());
    }

    #[Test]
    public function reorderedStepsProduceDifferentDiffHash(): void
    {
        $a = new CompiledMigrationPlan([
            new ExecuteStatement('A'),
            new ExecuteStatement('B'),
        ]);
        $b = new CompiledMigrationPlan([
            new ExecuteStatement('B'),
            new ExecuteStatement('A'),
        ]);

        self::assertNotSame($a->diffHash(), $b->diffHash());
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(CompiledMigrationPlan::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

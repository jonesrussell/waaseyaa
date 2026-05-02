<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\AddForeignKey;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;
use Waaseyaa\Foundation\Schema\Diff\AlterColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\DropForeignKey;
use Waaseyaa\Foundation\Schema\Diff\DropIndex;
use Waaseyaa\Foundation\Schema\Diff\ForeignKeySpec;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;
use Waaseyaa\Foundation\Schema\Diff\RenameTable;

#[CoversClass(CompositeDiff::class)]
final class CompositeDiffTest extends TestCase
{
    /**
     * Locked SHA-256 over the full-fixture canonical JSON. If this changes,
     * every recorded `checksum` / `diff_hash` in production ledgers diverges —
     * see {@see EmptyPlanTest} for the same warning.
     */
    private const FIXTURE_CHECKSUM = '700f61b1ea8a9ca9da1e03ab8a873fec21fcb43e283be3c451f4dd5bfa276fcf';

    private const SINGLE_ADD_COLUMN_CHECKSUM = '56776656d554d5efc2f16be616fdbce8215abce1ae1d1f5ec2cfc35e63f4a7b2';

    /**
     * @return list<\Waaseyaa\Foundation\Schema\Diff\SchemaDiffOp>
     */
    private static function fixtureOps(): array
    {
        return [
            new AddColumn('users', 'email', new ColumnSpec('varchar', false, null, 255)),
            new AddIndex('users', ['email'], 'idx_users_email', false),
            new DropColumn('users', 'legacy_flag'),
            new RenameTable('old_name', 'new_name'),
            new RenameColumn('users', 'name', 'full_name'),
            new AddForeignKey('orders', new ForeignKeySpec('users', ['user_id'], ['id'], 'CASCADE', null, 'fk_orders_user')),
            new DropForeignKey('orders', 'fk_orders_user_old'),
            new AlterColumn('users', 'age', new ColumnSpec('int', true)),
            new DropIndex('users', 'idx_legacy'),
        ];
    }

    #[Test]
    public function preservesOpInsertionOrder(): void
    {
        $ops = self::fixtureOps();
        $composite = new CompositeDiff($ops);

        self::assertSame($ops, $composite->ops);
    }

    #[Test]
    public function canonicalShapeWrapsOpsKey(): void
    {
        $composite = new CompositeDiff([
            new AddColumn('users', 'email', new ColumnSpec('varchar', false, null, 255)),
        ]);

        self::assertSame(
            [
                'ops' => [
                    [
                        'column' => 'email',
                        'kind' => 'add_column',
                        'spec' => [
                            'default' => null,
                            'length' => 255,
                            'nullable' => false,
                            'type' => 'varchar',
                        ],
                        'table' => 'users',
                    ],
                ],
            ],
            $composite->toCanonical(),
        );
    }

    #[Test]
    public function canonicalJsonIsByteStableForSingleAddColumn(): void
    {
        $composite = new CompositeDiff([
            new AddColumn('users', 'email', new ColumnSpec('varchar', false, null, 255)),
        ]);

        self::assertSame(
            '{"ops":[{"column":"email","kind":"add_column","spec":{"default":null,"length":255,"nullable":false,"type":"varchar"},"table":"users"}]}',
            $composite->toCanonicalJson(),
        );
        self::assertSame(self::SINGLE_ADD_COLUMN_CHECKSUM, $composite->checksum());
    }

    #[Test]
    public function checksumIsStableAcrossPhpRunsForLockedFixture(): void
    {
        $composite = new CompositeDiff(self::fixtureOps());

        self::assertSame(self::FIXTURE_CHECKSUM, $composite->checksum());
    }

    #[Test]
    public function equivalentCompositesHashIdentically(): void
    {
        $a = new CompositeDiff(self::fixtureOps());
        $b = new CompositeDiff(self::fixtureOps());

        self::assertTrue($a->equals($b));
        self::assertSame($a->checksum(), $b->checksum());
    }

    #[Test]
    public function reorderedOpsProduceDifferentChecksum(): void
    {
        $original = self::fixtureOps();
        $reordered = $original;
        [$reordered[0], $reordered[1]] = [$reordered[1], $reordered[0]];

        $a = new CompositeDiff($original);
        $b = new CompositeDiff($reordered);

        self::assertFalse(
            $a->equals($b),
            'Op order is part of plan identity; reordering must change the checksum.',
        );
        self::assertNotSame($a->checksum(), $b->checksum());
    }

    #[Test]
    public function emptyAndNonEmptyDifferInChecksum(): void
    {
        $empty = CompositeDiff::empty();
        $nonEmpty = new CompositeDiff([new DropColumn('users', 'x')]);

        self::assertFalse($empty->equals($nonEmpty));
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(CompositeDiff::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\DropIndex;
use Waaseyaa\Foundation\Schema\Diff\OpKind;

#[CoversClass(DropIndex::class)]
final class DropIndexTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $op = new DropIndex('users', 'idx_legacy', null);

        self::assertSame(OpKind::DropIndex, $op->kind());
        self::assertSame('users', $op->table);
        self::assertSame('idx_legacy', $op->name);
        self::assertNull($op->columns);
    }

    #[Test]
    public function canonicalShapeIsStableWithNamedIndex(): void
    {
        $op = new DropIndex('users', 'idx_legacy');

        self::assertSame(
            [
                'columns' => null,
                'kind' => 'drop_index',
                'name' => 'idx_legacy',
                'table' => 'users',
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function canonicalShapeIsStableWithColumnTuple(): void
    {
        $op = new DropIndex(table: 'users', columns: ['email', 'tenant_id']);

        self::assertSame(
            [
                'columns' => ['email', 'tenant_id'],
                'kind' => 'drop_index',
                'name' => null,
                'table' => 'users',
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(DropIndex::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

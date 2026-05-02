<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\DropForeignKey;
use Waaseyaa\Foundation\Schema\Diff\OpKind;

#[CoversClass(DropForeignKey::class)]
final class DropForeignKeyTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $op = new DropForeignKey('orders', 'fk_orders_user_old');

        self::assertSame(OpKind::DropForeignKey, $op->kind());
        self::assertSame('orders', $op->table);
        self::assertSame('fk_orders_user_old', $op->name);
    }

    #[Test]
    public function canonicalShapeIsStable(): void
    {
        $op = new DropForeignKey('orders', 'fk_orders_user_old');

        self::assertSame(
            [
                'kind' => 'drop_foreign_key',
                'name' => 'fk_orders_user_old',
                'table' => 'orders',
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(DropForeignKey::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

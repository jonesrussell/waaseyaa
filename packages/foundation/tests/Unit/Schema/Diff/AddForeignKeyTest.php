<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\AddForeignKey;
use Waaseyaa\Foundation\Schema\Diff\ForeignKeySpec;
use Waaseyaa\Foundation\Schema\Diff\OpKind;

#[CoversClass(AddForeignKey::class)]
#[CoversClass(ForeignKeySpec::class)]
final class AddForeignKeyTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $spec = new ForeignKeySpec('users', ['user_id'], ['id'], 'CASCADE', null, 'fk_orders_user');
        $op = new AddForeignKey('orders', $spec);

        self::assertSame(OpKind::AddForeignKey, $op->kind());
        self::assertSame('orders', $op->table);
        self::assertSame('users', $op->spec->referencedTable);
        self::assertSame(['user_id'], $op->spec->localColumns);
        self::assertSame(['id'], $op->spec->referencedColumns);
        self::assertSame('CASCADE', $op->spec->onDelete);
        self::assertNull($op->spec->onUpdate);
        self::assertSame('fk_orders_user', $op->spec->name);
    }

    #[Test]
    public function canonicalShapeIsStable(): void
    {
        $spec = new ForeignKeySpec('users', ['user_id'], ['id'], 'CASCADE', null, 'fk_orders_user');
        $op = new AddForeignKey('orders', $spec);

        self::assertSame(
            [
                'kind' => 'add_foreign_key',
                'spec' => [
                    'local_columns' => ['user_id'],
                    'name' => 'fk_orders_user',
                    'on_delete' => 'CASCADE',
                    'on_update' => null,
                    'referenced_columns' => ['id'],
                    'referenced_table' => 'users',
                ],
                'table' => 'orders',
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function specDefaultsAreNull(): void
    {
        $spec = new ForeignKeySpec('users', ['user_id'], ['id']);

        self::assertNull($spec->onDelete);
        self::assertNull($spec->onUpdate);
        self::assertNull($spec->name);
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $opReflection = new \ReflectionClass(AddForeignKey::class);
        $specReflection = new \ReflectionClass(ForeignKeySpec::class);

        self::assertTrue($opReflection->isReadOnly());
        self::assertTrue($opReflection->isFinal());
        self::assertTrue($specReflection->isReadOnly());
        self::assertTrue($specReflection->isFinal());
    }
}

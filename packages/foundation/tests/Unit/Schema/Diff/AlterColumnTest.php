<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\AlterColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\OpKind;

#[CoversClass(AlterColumn::class)]
final class AlterColumnTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $op = new AlterColumn('users', 'age', new ColumnSpec('int', true));

        self::assertSame(OpKind::AlterColumn, $op->kind());
        self::assertSame('users', $op->table);
        self::assertSame('age', $op->column);
        self::assertTrue($op->newSpec->nullable);
        self::assertSame('int', $op->newSpec->type);
    }

    #[Test]
    public function canonicalShapeIsStable(): void
    {
        $op = new AlterColumn('users', 'age', new ColumnSpec('int', true));

        self::assertSame(
            [
                'column' => 'age',
                'kind' => 'alter_column',
                'new_spec' => [
                    'default' => null,
                    'length' => null,
                    'nullable' => true,
                    'type' => 'int',
                ],
                'table' => 'users',
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(AlterColumn::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

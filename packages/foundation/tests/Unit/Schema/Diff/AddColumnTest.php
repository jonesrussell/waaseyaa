<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\OpKind;

#[CoversClass(AddColumn::class)]
#[CoversClass(ColumnSpec::class)]
final class AddColumnTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $op = new AddColumn('users', 'email', new ColumnSpec('varchar', false, null, 255));

        self::assertSame(OpKind::AddColumn, $op->kind());
        self::assertSame('users', $op->table);
        self::assertSame('email', $op->column);
        self::assertSame('varchar', $op->spec->type);
        self::assertSame(255, $op->spec->length);
        self::assertFalse($op->spec->nullable);
    }

    #[Test]
    public function canonicalShapeIsStable(): void
    {
        $op = new AddColumn('users', 'email', new ColumnSpec('varchar', false, null, 255));

        self::assertSame(
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
            $op->toCanonical(),
        );
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(AddColumn::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

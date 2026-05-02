<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\OpKind;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;

#[CoversClass(RenameColumn::class)]
final class RenameColumnTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $op = new RenameColumn('users', 'name', 'full_name');

        self::assertSame(OpKind::RenameColumn, $op->kind());
        self::assertSame('users', $op->table);
        self::assertSame('name', $op->from);
        self::assertSame('full_name', $op->to);
    }

    #[Test]
    public function canonicalShapeIsStable(): void
    {
        $op = new RenameColumn('users', 'name', 'full_name');

        self::assertSame(
            [
                'from' => 'name',
                'kind' => 'rename_column',
                'table' => 'users',
                'to' => 'full_name',
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(RenameColumn::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

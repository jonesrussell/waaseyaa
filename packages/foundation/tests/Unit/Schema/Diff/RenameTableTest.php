<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\OpKind;
use Waaseyaa\Foundation\Schema\Diff\RenameTable;

#[CoversClass(RenameTable::class)]
final class RenameTableTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $op = new RenameTable('old_name', 'new_name');

        self::assertSame(OpKind::RenameTable, $op->kind());
        self::assertSame('old_name', $op->from);
        self::assertSame('new_name', $op->to);
    }

    #[Test]
    public function canonicalShapeIsStable(): void
    {
        $op = new RenameTable('old_name', 'new_name');

        self::assertSame(
            [
                'from' => 'old_name',
                'kind' => 'rename_table',
                'to' => 'new_name',
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(RenameTable::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

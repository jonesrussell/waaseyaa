<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\OpKind;

#[CoversClass(DropColumn::class)]
final class DropColumnTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $op = new DropColumn('users', 'legacy_flag');

        self::assertSame(OpKind::DropColumn, $op->kind());
        self::assertSame('users', $op->table);
        self::assertSame('legacy_flag', $op->column);
    }

    #[Test]
    public function canonicalShapeIsStable(): void
    {
        $op = new DropColumn('users', 'legacy_flag');

        self::assertSame(
            [
                'column' => 'legacy_flag',
                'kind' => 'drop_column',
                'table' => 'users',
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(DropColumn::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

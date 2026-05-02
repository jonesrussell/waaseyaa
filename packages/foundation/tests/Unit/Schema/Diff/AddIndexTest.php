<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Diff;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;
use Waaseyaa\Foundation\Schema\Diff\OpKind;

#[CoversClass(AddIndex::class)]
final class AddIndexTest extends TestCase
{
    #[Test]
    public function exposesKindAndConstructorArgs(): void
    {
        $op = new AddIndex('users', ['email', 'tenant_id'], 'idx_users_email_tenant', true);

        self::assertSame(OpKind::AddIndex, $op->kind());
        self::assertSame('users', $op->table);
        self::assertSame(['email', 'tenant_id'], $op->columns);
        self::assertSame('idx_users_email_tenant', $op->name);
        self::assertTrue($op->unique);
    }

    #[Test]
    public function canonicalShapeIsStable(): void
    {
        $op = new AddIndex('users', ['email'], 'idx_users_email', false);

        self::assertSame(
            [
                'columns' => ['email'],
                'kind' => 'add_index',
                'name' => 'idx_users_email',
                'table' => 'users',
                'unique' => false,
            ],
            $op->toCanonical(),
        );
    }

    #[Test]
    public function nameDefaultsToNullForAnonymousIndex(): void
    {
        $op = new AddIndex('users', ['email']);

        self::assertNull($op->name);
        self::assertFalse($op->unique);
        self::assertNull($op->toCanonical()['name']);
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(AddIndex::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is not readonly');
        }
    }
}

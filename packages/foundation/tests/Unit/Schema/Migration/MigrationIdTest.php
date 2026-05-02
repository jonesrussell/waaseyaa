<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Migration\MigrationId;

#[CoversClass(MigrationId::class)]
final class MigrationIdTest extends TestCase
{
    #[Test]
    public function roundTripsValidValue(): void
    {
        $id = new MigrationId('waaseyaa/groups:v2:add-archived-flag');

        self::assertSame('waaseyaa/groups:v2:add-archived-flag', $id->toString());
        self::assertSame('waaseyaa/groups:v2:add-archived-flag', $id->value);
        self::assertSame('waaseyaa/groups:v2:add-archived-flag', (string) $id);
    }

    #[Test]
    public function fromStringFactoryConstructsValueObject(): void
    {
        $id = MigrationId::fromString('vendor/pkg:v2:abc');

        self::assertSame('vendor/pkg:v2:abc', $id->toString());
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(MigrationId::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validIdProvider(): iterable
    {
        yield 'short kebab' => ['v/p:v2:a'];
        yield 'full canonical' => ['waaseyaa/groups:v2:add-archived-flag'];
        yield 'numeric slug' => ['waaseyaa/foo:v2:1234'];
        yield 'mixed kebab' => ['waaseyaa/entity-storage:v2:rename-bundle-subtable'];
        yield 'digits in vendor' => ['ven1/pkg:v2:slug-42'];
    }

    #[Test]
    #[DataProvider('validIdProvider')]
    public function acceptsValidFormats(string $valid): void
    {
        $id = new MigrationId($valid);

        self::assertSame($valid, $id->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'no v2 marker' => ['waaseyaa/groups:add-archived-flag'];
        yield 'wrong version marker' => ['waaseyaa/groups:v3:add-archived-flag'];
        yield 'uppercase vendor' => ['Waaseyaa/groups:v2:add-archived-flag'];
        yield 'uppercase package' => ['waaseyaa/Groups:v2:add-archived-flag'];
        yield 'uppercase slug' => ['waaseyaa/groups:v2:Add-Archived-Flag'];
        yield 'missing vendor' => ['/groups:v2:add-archived-flag'];
        yield 'missing package' => ['waaseyaa/:v2:add-archived-flag'];
        yield 'missing slug' => ['waaseyaa/groups:v2:'];
        yield 'slug starts with hyphen' => ['waaseyaa/groups:v2:-add'];
        yield 'whitespace' => ['waaseyaa/groups:v2: add'];
        yield 'underscore in slug' => ['waaseyaa/groups:v2:add_archived'];
        yield 'colon in slug' => ['waaseyaa/groups:v2:add:archived'];
        yield 'dot in vendor' => ['waaseyaa.org/groups:v2:add-archived'];
    }

    #[Test]
    #[DataProvider('invalidIdProvider')]
    public function rejectsInvalidFormatsWithClearError(string $invalid): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid migration_id/');

        new MigrationId($invalid);
    }
}

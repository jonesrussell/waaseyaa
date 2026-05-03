<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCapabilities;

#[CoversClass(SqliteCapabilities::class)]
final class SqliteCapabilitiesTest extends TestCase
{
    #[Test]
    public function modernVersionSupportsRenameColumn(): void
    {
        $caps = SqliteCapabilities::forVersion('3.40.0');

        self::assertTrue($caps->supportsRenameColumn);
        self::assertSame('3.40.0', $caps->version);
        self::assertFalse($caps->foreignKeysEnabled);
        self::assertTrue($caps->supportsDropColumn);
    }

    #[Test]
    public function exactlyVersion335SupportsDropColumn(): void
    {
        $caps = SqliteCapabilities::forVersion('3.35.0');

        self::assertTrue($caps->supportsDropColumn);
    }

    #[Test]
    public function versionBelow335RejectsDropColumn(): void
    {
        $caps = SqliteCapabilities::forVersion('3.34.0');

        self::assertFalse($caps->supportsDropColumn);
        // Rename column is still available since 3.25 < 3.34 < 3.35.
        self::assertTrue($caps->supportsRenameColumn);
    }

    #[Test]
    public function exactlyVersion325SupportsRenameColumn(): void
    {
        $caps = SqliteCapabilities::forVersion('3.25.0');

        self::assertTrue($caps->supportsRenameColumn);
    }

    #[Test]
    public function versionBelow325RejectsRenameColumn(): void
    {
        $caps = SqliteCapabilities::forVersion('3.24.0');

        self::assertFalse($caps->supportsRenameColumn);
    }

    #[Test]
    public function preReleaseSuffixComparesLowerThanBareVersion(): void
    {
        // version_compare('3.25.0-beta', '3.25.0', '<') is true — pre-release
        // suffixes sort below the release. The capability gate flips off.
        $caps = SqliteCapabilities::forVersion('3.25.0-beta');

        self::assertFalse($caps->supportsRenameColumn);
    }

    #[Test]
    public function constructorAcceptsExplicitOverrides(): void
    {
        $caps = new SqliteCapabilities(
            version: '3.40.0',
            supportsRenameColumn: false,
            foreignKeysEnabled: true,
        );

        self::assertSame('3.40.0', $caps->version);
        self::assertFalse($caps->supportsRenameColumn);
        self::assertTrue($caps->foreignKeysEnabled);
    }

    #[Test]
    public function classAndPropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(SqliteCapabilities::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }
}

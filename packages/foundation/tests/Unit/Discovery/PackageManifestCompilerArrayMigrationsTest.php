<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Discovery\InvalidMigrationEntryException;
use Waaseyaa\Foundation\Discovery\PackageManifestCompiler;

/**
 * Mission #529 / WP11 / T066 + T068 (compiler half).
 *
 * Locks the `extra.waaseyaa.migrations` entry validation contract.
 * The string and ordered-list shapes pass through unchanged; every
 * other shape (object, nested array, non-string scalar, associative
 * array) raises {@see InvalidMigrationEntryException} with stable
 * code `INVALID_MIGRATION_ENTRY`.
 */
#[CoversClass(PackageManifestCompiler::class)]
#[CoversClass(InvalidMigrationEntryException::class)]
final class PackageManifestCompilerArrayMigrationsTest extends TestCase
{
    #[Test]
    public function singleStringEntryIsAccepted(): void
    {
        self::assertSame(
            'migrations',
            PackageManifestCompiler::validateMigrationsEntry('vendor/pkg', 'migrations'),
        );
    }

    #[Test]
    public function listOfStringsIsAcceptedAndOrderPreserved(): void
    {
        $entry = ['Z\\Migrations', 'A\\Migrations', '../patches/v2'];

        self::assertSame(
            $entry,
            PackageManifestCompiler::validateMigrationsEntry('vendor/pkg', $entry),
            'Order must be preserved as authored — no alphabetisation.',
        );
    }

    #[Test]
    public function emptyArrayIsAccepted(): void
    {
        self::assertSame([], PackageManifestCompiler::validateMigrationsEntry('vendor/pkg', []));
    }

    #[Test]
    public function nonStringEntryIsRejectedWithStableCode(): void
    {
        $thrown = null;
        try {
            PackageManifestCompiler::validateMigrationsEntry('vendor/pkg', ['valid', ['type' => 'namespace']]);
        } catch (InvalidMigrationEntryException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame('INVALID_MIGRATION_ENTRY', $thrown->diagnosticCode());
        self::assertSame('vendor/pkg', $thrown->packageName);
        self::assertStringContainsString('index 1', $thrown->getMessage());
    }

    #[Test]
    public function associativeArrayIsRejected(): void
    {
        $this->expectException(InvalidMigrationEntryException::class);

        PackageManifestCompiler::validateMigrationsEntry('vendor/pkg', ['key' => 'migrations']);
    }

    #[Test]
    public function nonStringNonArrayValueIsRejected(): void
    {
        $thrown = null;
        try {
            PackageManifestCompiler::validateMigrationsEntry('vendor/pkg', 42);
        } catch (InvalidMigrationEntryException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertStringContainsString('expected string or list of strings', $thrown->getMessage());
    }
}

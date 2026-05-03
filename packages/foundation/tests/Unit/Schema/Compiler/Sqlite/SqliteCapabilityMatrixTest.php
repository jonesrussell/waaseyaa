<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCapabilities;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCapabilityMatrix;

/**
 * Locks the per-version checkpoint flags. Drift between this test and
 * `docs/specs/sqlite-capability-matrix.md` is a code smell — both
 * surfaces document the same truth.
 */
#[CoversClass(SqliteCapabilityMatrix::class)]
final class SqliteCapabilityMatrixTest extends TestCase
{
    #[Test]
    public function sqlite30HasNoModernCapabilities(): void
    {
        $caps = SqliteCapabilityMatrix::sqlite30();

        self::assertSame('3.0.0', $caps->version);
        self::assertFalse($caps->supportsRenameColumn);
        self::assertFalse($caps->supportsDropColumn);
    }

    #[Test]
    public function sqlite321IsPreRenameColumn(): void
    {
        $caps = SqliteCapabilityMatrix::sqlite321();

        self::assertFalse($caps->supportsRenameColumn);
        self::assertFalse($caps->supportsDropColumn);
    }

    #[Test]
    public function sqlite325GainsRenameColumn(): void
    {
        $caps = SqliteCapabilityMatrix::sqlite325();

        self::assertTrue($caps->supportsRenameColumn);
        self::assertFalse($caps->supportsDropColumn);
    }

    #[Test]
    public function sqlite335GainsDropColumn(): void
    {
        $caps = SqliteCapabilityMatrix::sqlite335();

        self::assertTrue($caps->supportsRenameColumn);
        self::assertTrue($caps->supportsDropColumn);
    }

    #[Test]
    public function sqlite340HasFullModernSet(): void
    {
        $caps = SqliteCapabilityMatrix::sqlite340();

        self::assertTrue($caps->supportsRenameColumn);
        self::assertTrue($caps->supportsDropColumn);
    }

    #[Test]
    public function sqlite350HasFullModernSet(): void
    {
        $caps = SqliteCapabilityMatrix::sqlite350();

        self::assertTrue($caps->supportsRenameColumn);
        self::assertTrue($caps->supportsDropColumn);
    }

    #[Test]
    public function genericForFactoryDelegatesToCapabilities(): void
    {
        $caps = SqliteCapabilityMatrix::for('3.45.2');

        self::assertSame('3.45.2', $caps->version);
        self::assertTrue($caps->supportsRenameColumn);
        self::assertTrue($caps->supportsDropColumn);
        self::assertInstanceOf(SqliteCapabilities::class, $caps);
    }
}

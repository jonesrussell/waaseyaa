<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\EntityStorage\Exception\StorageMigrationException;

/**
 * Unit tests for the WP04 storage-migration exception surface
 * (FR-040 / FR-041, contracts/exception-surface.md §4).
 */
#[CoversClass(StorageMigrationException::class)]
final class StorageMigrationExceptionTest extends TestCase
{
    #[Test]
    public function noOpPromotionCarriesStableErrorCode(): void
    {
        $ex = StorageMigrationException::noOpPromotion('teaching');

        self::assertInstanceOf(StorageMigrationException::class, $ex);
        self::assertSame('no_op_promotion', $ex->errorCode);
        self::assertStringContainsString('teaching', $ex->getMessage());
        self::assertStringContainsString('already two-axis', $ex->getMessage());
    }

    #[Test]
    public function unsupportedTwoAxisFieldCarriesStableErrorCode(): void
    {
        $ex = StorageMigrationException::unsupportedTwoAxisField('embedding', 'vector');

        self::assertInstanceOf(StorageMigrationException::class, $ex);
        self::assertSame('unsupported_two_axis_field', $ex->errorCode);
        self::assertStringContainsString('embedding', $ex->getMessage());
        self::assertStringContainsString('vector', $ex->getMessage());
        self::assertStringContainsString('sql-column', $ex->getMessage());
        self::assertStringContainsString('sql-blob', $ex->getMessage());
    }

    #[Test]
    public function unsupportedTwoAxisFieldMessageRetainsMarkerToken(): void
    {
        // WP01/WP02 contract tests assert on the literal token
        // `unsupportedTwoAxisField` in the exception message. The typed
        // exception preserves this marker so the WP01 RuntimeException trigger
        // sites can be lifted in-place by WP05+ without breaking those tests.
        $ex = StorageMigrationException::unsupportedTwoAxisField('embedding', 'vector');

        self::assertStringContainsString('unsupportedTwoAxisField', $ex->getMessage());
    }

    #[Test]
    public function extendsRuntimeExceptionForBackwardCompatibility(): void
    {
        // WP01/WP02 callers and tests catch \RuntimeException at the marker
        // trigger sites. Keeping this inheritance preserves the existing
        // catch surface as it migrates to the typed factory.
        $ex = StorageMigrationException::noOpPromotion('teaching');

        self::assertInstanceOf(\RuntimeException::class, $ex);
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(StorageMigrationException::class);

        self::assertTrue($reflection->isFinal(), 'StorageMigrationException must be final.');
    }

    #[Test]
    public function constructorIsPrivate(): void
    {
        $reflection = new \ReflectionClass(StorageMigrationException::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate(), 'Constructor must be private; factories are the only construction path.');
    }

    #[Test]
    public function errorCodeIsReadonly(): void
    {
        $reflection = new \ReflectionProperty(StorageMigrationException::class, 'errorCode');

        self::assertTrue($reflection->isReadOnly(), 'errorCode must be readonly to preserve stable surface.');
    }
}

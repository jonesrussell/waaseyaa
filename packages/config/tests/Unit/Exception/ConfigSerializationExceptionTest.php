<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Exception\ConfigSerializationException;

#[CoversClass(ConfigSerializationException::class)]
final class ConfigSerializationExceptionTest extends TestCase
{
    #[Test]
    public function entityTypeMismatchIncludesAllRelevantContext(): void
    {
        $exception = ConfigSerializationException::entityTypeMismatch(
            'role.admin.yml',
            'permission',
            'role',
        );

        self::assertStringContainsString('role.admin.yml', $exception->getMessage());
        self::assertStringContainsString('"permission"', $exception->getMessage());
        self::assertStringContainsString('"role"', $exception->getMessage());
    }

    #[Test]
    public function missingMetaKeyNamesTheKey(): void
    {
        $exception = ConfigSerializationException::missingMetaKey('role.admin.yml', 'uuid');

        self::assertStringContainsString('role.admin.yml', $exception->getMessage());
        self::assertStringContainsString('"uuid"', $exception->getMessage());
    }

    #[Test]
    public function invalidFilenameSurfaceTheFilename(): void
    {
        $exception = ConfigSerializationException::invalidFilename('NOT-A-VALID-NAME.txt');

        self::assertStringContainsString('NOT-A-VALID-NAME.txt', $exception->getMessage());
    }

    #[Test]
    public function strayFieldNamesFieldAndFile(): void
    {
        $exception = ConfigSerializationException::strayField('role.admin.yml', 'mystery_field');

        self::assertStringContainsString('mystery_field', $exception->getMessage());
        self::assertStringContainsString('role.admin.yml', $exception->getMessage());
    }

    #[Test]
    public function typeMismatchDescribesExpectedAndActual(): void
    {
        $exception = ConfigSerializationException::typeMismatch('weight', 'int', 'string');

        self::assertStringContainsString('weight', $exception->getMessage());
        self::assertStringContainsString('int', $exception->getMessage());
        self::assertStringContainsString('string', $exception->getMessage());
    }

    #[Test]
    public function malformedYamlIncludesReason(): void
    {
        $exception = ConfigSerializationException::malformedYaml('role.admin.yml', 'tab character at line 3');

        self::assertStringContainsString('role.admin.yml', $exception->getMessage());
        self::assertStringContainsString('tab character at line 3', $exception->getMessage());
    }

    #[Test]
    public function missingMetaBlockReportsTheFilename(): void
    {
        $exception = ConfigSerializationException::missingMetaBlock('role.admin.yml');

        self::assertStringContainsString('role.admin.yml', $exception->getMessage());
        self::assertStringContainsString('_meta', $exception->getMessage());
    }

    #[Test]
    public function isRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, new ConfigSerializationException('x'));
    }
}

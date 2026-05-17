<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Dependency\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Dependency\Exception\ConfigDependencyMissingException;

#[CoversClass(ConfigDependencyMissingException::class)]
final class ConfigDependencyMissingExceptionTest extends TestCase
{
    #[Test]
    public function defaultCodeIsStableString(): void
    {
        $exception = new ConfigDependencyMissingException(
            missingRef: 'taxonomy_vocabulary.ghost',
            requiredBy: 'menu.main',
        );

        self::assertSame('config.dependency.missing', $exception->errorCode);
        // `code` is a virtual accessor (via __get) aliasing $errorCode; the
        // inherited integer \Exception::$code is reachable only via getCode().
        self::assertSame('config.dependency.missing', $exception->code);
        self::assertSame(0, $exception->getCode());
    }

    #[Test]
    public function refsArePreserved(): void
    {
        $exception = new ConfigDependencyMissingException(
            missingRef: 'taxonomy_vocabulary.ghost',
            requiredBy: 'menu.main',
        );

        self::assertSame('taxonomy_vocabulary.ghost', $exception->missingRef);
        self::assertSame('menu.main', $exception->requiredBy);
    }

    #[Test]
    public function messageMentionsBothRefs(): void
    {
        $exception = new ConfigDependencyMissingException(
            missingRef: 'role.editor',
            requiredBy: 'permission.publish',
        );

        $message = $exception->getMessage();
        self::assertStringContainsString("'role.editor'", $message);
        self::assertStringContainsString("'permission.publish'", $message);
        self::assertStringContainsString('not present in sync store or active store', $message);
    }

    #[Test]
    public function exceptionIsRuntimeException(): void
    {
        $exception = new ConfigDependencyMissingException(
            missingRef: 'a.a',
            requiredBy: 'b.b',
        );
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function previousIsPreserved(): void
    {
        $previous = new \RuntimeException('underlying');
        $exception = new ConfigDependencyMissingException(
            missingRef: 'a.a',
            requiredBy: 'b.b',
            previous: $previous,
        );

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function customErrorCodeIsAccepted(): void
    {
        $exception = new ConfigDependencyMissingException(
            missingRef: 'a.a',
            requiredBy: 'b.b',
            errorCode: 'custom.missing.code',
        );

        self::assertSame('custom.missing.code', $exception->errorCode);
        self::assertSame('custom.missing.code', $exception->code);
    }
}

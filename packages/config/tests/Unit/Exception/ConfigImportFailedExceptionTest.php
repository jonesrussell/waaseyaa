<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Exception\ConfigImportFailedException;

#[CoversClass(ConfigImportFailedException::class)]
final class ConfigImportFailedExceptionTest extends TestCase
{
    #[Test]
    public function default_error_code_is_stable_string(): void
    {
        $exception = new ConfigImportFailedException(
            ref: 'role.admin',
            reason: 'boom',
        );

        self::assertSame('role.admin', $exception->ref);
        self::assertSame('config.import.failed', $exception->errorCode);
        self::assertSame(0, $exception->getCode(), 'Integer code mirrors PHP convention.');
        self::assertStringContainsString('role.admin', $exception->getMessage());
        self::assertStringContainsString('boom', $exception->getMessage());
    }

    #[Test]
    public function apply_failed_factory_sets_subcode(): void
    {
        $previous = new \RuntimeException('db crashed');
        $exception = ConfigImportFailedException::applyFailed('node.welcome', 'db crashed', $previous);

        self::assertSame('config.import.apply_failed', $exception->errorCode);
        self::assertSame('node.welcome', $exception->ref);
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function validation_failed_factory_sets_subcode(): void
    {
        $exception = ConfigImportFailedException::validationFailed('role.admin', 'field "label" is empty');

        self::assertSame('config.import.validation_failed', $exception->errorCode);
        self::assertStringContainsString('field "label"', $exception->getMessage());
    }

    #[Test]
    public function transaction_failed_factory_sets_subcode(): void
    {
        $exception = ConfigImportFailedException::transactionFailed('role.admin', 'commit refused');

        self::assertSame('config.import.transaction_failed', $exception->errorCode);
    }

    #[Test]
    public function exception_is_runtime_exception_subclass(): void
    {
        $exception = new ConfigImportFailedException(ref: 'role.admin', reason: 'x');
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function custom_error_code_is_preserved(): void
    {
        $exception = new ConfigImportFailedException(
            ref: 'role.admin',
            reason: 'x',
            errorCode: 'config.import.custom',
        );
        self::assertSame('config.import.custom', $exception->errorCode);
    }
}

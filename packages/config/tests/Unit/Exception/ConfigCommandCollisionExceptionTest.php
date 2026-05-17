<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Exception\ConfigCommandCollisionException;

#[CoversClass(ConfigCommandCollisionException::class)]
final class ConfigCommandCollisionExceptionTest extends TestCase
{
    #[Test]
    public function constructor_stores_reserved_verb_and_offending_fqcn(): void
    {
        $exception = new ConfigCommandCollisionException(
            reservedVerb: 'config:export',
            offendingFqcn: 'App\\Console\\MyExportCommand',
        );

        self::assertSame('config:export', $exception->reservedVerb);
        self::assertSame('App\\Console\\MyExportCommand', $exception->offendingFqcn);
    }

    #[Test]
    public function error_code_is_stable_string(): void
    {
        $exception = new ConfigCommandCollisionException(
            reservedVerb: 'config:import',
            offendingFqcn: 'Foo\\Bar',
        );

        self::assertSame('config.cli.collision', $exception->errorCode);
        self::assertSame('config.cli.collision', ConfigCommandCollisionException::ERROR_CODE);
        self::assertSame(0, $exception->getCode(), 'Integer code mirrors PHP convention.');
    }

    #[Test]
    public function message_mentions_both_verb_and_fqcn(): void
    {
        $exception = new ConfigCommandCollisionException(
            reservedVerb: 'config:diff',
            offendingFqcn: 'App\\Console\\OtherDiff',
        );

        self::assertStringContainsString('config:diff', $exception->getMessage());
        self::assertStringContainsString('App\\Console\\OtherDiff', $exception->getMessage());
    }

    #[Test]
    public function factory_for_verb_returns_equivalent_instance(): void
    {
        $exception = ConfigCommandCollisionException::forVerb('config:reset', 'App\\Reset');

        self::assertInstanceOf(ConfigCommandCollisionException::class, $exception);
        self::assertSame('config:reset', $exception->reservedVerb);
        self::assertSame('App\\Reset', $exception->offendingFqcn);
        self::assertSame('config.cli.collision', $exception->errorCode);
    }

    #[Test]
    public function exception_is_logic_exception_subclass(): void
    {
        $exception = new ConfigCommandCollisionException(
            reservedVerb: 'config:status',
            offendingFqcn: 'X',
        );

        self::assertInstanceOf(\LogicException::class, $exception);
        self::assertInstanceOf(\Throwable::class, $exception);
    }
}

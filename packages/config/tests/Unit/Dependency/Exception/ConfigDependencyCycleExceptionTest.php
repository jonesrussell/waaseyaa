<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Dependency\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Dependency\Exception\ConfigDependencyCycleException;

#[CoversClass(ConfigDependencyCycleException::class)]
final class ConfigDependencyCycleExceptionTest extends TestCase
{
    #[Test]
    public function defaultCodeIsStableString(): void
    {
        $exception = new ConfigDependencyCycleException(cyclePath: ['a.a', 'b.b', 'a.a']);
        self::assertSame('config.dependency.cycle', $exception->errorCode);
        // `code` is a virtual accessor (via __get) aliasing $errorCode; the
        // inherited integer \Exception::$code is reachable only via getCode().
        self::assertSame('config.dependency.cycle', $exception->code);
        self::assertSame(0, $exception->getCode());
    }

    #[Test]
    public function getCycleReturnsFullPathUntruncated(): void
    {
        $path = ['a.a', 'b.b', 'c.c', 'd.d', 'e.e', 'f.f', 'g.g', 'a.a'];
        $exception = new ConfigDependencyCycleException(cyclePath: $path);
        self::assertSame($path, $exception->getCycle());
    }

    #[Test]
    public function messageRendersShortCycleWithArrows(): void
    {
        $exception = new ConfigDependencyCycleException(
            cyclePath: ['role.admin', 'permission.x', 'role.admin'],
        );

        self::assertStringContainsString(
            'Config dependency cycle:',
            $exception->getMessage(),
        );
        self::assertStringContainsString(
            'role.admin → permission.x → role.admin',
            $exception->getMessage(),
        );
        self::assertStringNotContainsString('…', $exception->getMessage());
    }

    #[Test]
    public function messageTruncatesLongCycleWithEllipsis(): void
    {
        // 7 distinct nodes + closing repeat = 8 entries; exceeds 5-hop limit.
        $exception = new ConfigDependencyCycleException(
            cyclePath: ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'a'],
        );

        self::assertStringContainsString('…', $exception->getMessage());
        // Head: first 5 entries rendered.
        self::assertStringContainsString('a → b → c → d → e', $exception->getMessage());
        // Tail: closes with the first node.
        self::assertStringEndsWith('… → a', $exception->getMessage());
    }

    #[Test]
    public function exceptionIsRuntimeException(): void
    {
        $exception = new ConfigDependencyCycleException(cyclePath: ['a.a', 'b.b', 'a.a']);
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function previousIsPreserved(): void
    {
        $previous = new \RuntimeException('underlying');
        $exception = new ConfigDependencyCycleException(
            cyclePath: ['a.a', 'b.b', 'a.a'],
            previous: $previous,
        );
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function customErrorCodeIsAccepted(): void
    {
        $exception = new ConfigDependencyCycleException(
            cyclePath: ['a.a', 'b.b', 'a.a'],
            errorCode: 'custom.cycle.code',
        );
        self::assertSame('custom.cycle.code', $exception->errorCode);
        self::assertSame('custom.cycle.code', $exception->code);
    }
}

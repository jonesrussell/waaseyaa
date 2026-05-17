<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Exception\InvalidConfigBackendException;

#[CoversClass(InvalidConfigBackendException::class)]
final class InvalidConfigBackendExceptionTest extends TestCase
{
    #[Test]
    public function carriesEntityTypeIdBackendIdAndDeclaringFqcn(): void
    {
        $exception = new InvalidConfigBackendException(
            entityTypeId: 'node_type',
            backendId: 'vector',
            declaringFqcn: 'App\\Entity\\NodeType',
            allowedBackendIds: ['sql-blob', 'sql-column'],
        );

        self::assertSame('node_type', $exception->getEntityTypeId());
        self::assertSame('vector', $exception->getBackendId());
        self::assertSame('App\\Entity\\NodeType', $exception->getDeclaringFqcn());
        self::assertSame(['sql-blob', 'sql-column'], $exception->getAllowedBackendIds());
    }

    #[Test]
    public function messageIncludesAllDiagnosticDetail(): void
    {
        $exception = new InvalidConfigBackendException(
            entityTypeId: 'view',
            backendId: 'remote',
            declaringFqcn: 'App\\Entity\\View',
            allowedBackendIds: ['sql-blob', 'sql-column'],
        );

        $message = $exception->getMessage();

        self::assertStringContainsString('view', $message);
        self::assertStringContainsString('App\\Entity\\View', $message);
        self::assertStringContainsString('remote', $message);
        self::assertStringContainsString('sql-blob', $message);
        self::assertStringContainsString('sql-column', $message);
    }

    #[Test]
    public function isARuntimeException(): void
    {
        $exception = new InvalidConfigBackendException(
            entityTypeId: 't',
            backendId: 'b',
            declaringFqcn: 'Foo',
            allowedBackendIds: ['sql-blob'],
        );

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function preservesPreviousException(): void
    {
        $previous = new \LogicException('upstream');
        $exception = new InvalidConfigBackendException(
            entityTypeId: 't',
            backendId: 'b',
            declaringFqcn: 'Foo',
            allowedBackendIds: ['sql-blob'],
            previous: $previous,
        );

        self::assertSame($previous, $exception->getPrevious());
    }
}

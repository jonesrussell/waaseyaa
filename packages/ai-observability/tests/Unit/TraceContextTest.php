<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Handle\TraceHandle;
use Waaseyaa\AI\Observability\TraceContext;

#[CoversClass(TraceContext::class)]
final class TraceContextTest extends TestCase
{
    #[Test]
    public function registersAndRetrievesHandle(): void
    {
        $ctx = new TraceContext();
        $handle = new TraceHandle('t-1', new \DateTimeImmutable());
        $ctx->register($handle);

        self::assertSame($handle, $ctx->get('t-1'));
    }

    #[Test]
    public function returnsNullForUnknownUuid(): void
    {
        $ctx = new TraceContext();
        self::assertNull($ctx->get('nope'));
    }

    #[Test]
    public function clearRemovesHandle(): void
    {
        $ctx = new TraceContext();
        $handle = new TraceHandle('t-1', new \DateTimeImmutable());
        $ctx->register($handle);
        $ctx->clear('t-1');
        self::assertNull($ctx->get('t-1'));
    }
}

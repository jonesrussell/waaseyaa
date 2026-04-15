<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Handle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Handle\TraceHandle;

#[CoversClass(TraceHandle::class)]
final class TraceHandleTest extends TestCase
{
    #[Test]
    public function it_holds_uuid_and_started_at(): void
    {
        $startedAt = new \DateTimeImmutable('2026-04-14T12:00:00Z');
        $handle = new TraceHandle('abc-123', $startedAt);

        self::assertSame('abc-123', $handle->uuid);
        self::assertSame($startedAt, $handle->startedAt);
    }
}

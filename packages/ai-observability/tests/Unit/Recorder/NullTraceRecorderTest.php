<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Recorder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Recorder\NullTraceRecorder;
use Waaseyaa\AI\Observability\Value\DecisionTrace;
use Waaseyaa\AI\Observability\Value\Outcome;

#[CoversClass(NullTraceRecorder::class)]
final class NullTraceRecorderTest extends TestCase
{
    #[Test]
    public function allOperationsAreNoops(): void
    {
        $r = new NullTraceRecorder();
        $trace = $r->startTrace('test');
        $span = $r->span($trace, 'tool_call', 'foo');
        $r->endSpan($span, ['x' => 1]);
        $r->recordDecision($trace, new DecisionTrace('q', 'a', [], 'r', 0.5));
        $r->recordOutcome($trace, new Outcome('accepted'));
        $r->completeTrace($trace);

        self::assertSame('disabled', $trace->uuid);
        self::assertSame('disabled', $span->uuid);
    }
}

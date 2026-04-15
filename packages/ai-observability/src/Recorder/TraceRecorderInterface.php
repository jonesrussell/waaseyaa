<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Recorder;

use Waaseyaa\AI\Observability\Handle\SpanHandle;
use Waaseyaa\AI\Observability\Handle\TraceHandle;
use Waaseyaa\AI\Observability\Value\DecisionTrace;
use Waaseyaa\AI\Observability\Value\Outcome;

interface TraceRecorderInterface
{
    /** @param array<string, mixed> $attributes */
    public function startTrace(string $label, array $attributes = []): TraceHandle;

    public function completeTrace(TraceHandle $handle, string $status = 'ok'): void;

    public function span(TraceHandle $handle, string $kind, string $name, ?SpanHandle $parent = null): SpanHandle;

    /** @param array<string, mixed> $attributes */
    public function endSpan(SpanHandle $handle, array $attributes = [], string $status = 'ok'): void;

    public function recordDecision(TraceHandle $handle, DecisionTrace $decision): void;

    public function recordOutcome(TraceHandle $handle, Outcome $outcome): void;
}

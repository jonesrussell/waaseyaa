<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Handle;

/**
 * Opaque handle returned by TraceRecorder::startTrace().
 * Passed back to complete the trace or open child spans.
 * @api
 */
final readonly class TraceHandle
{
    public function __construct(
        public string $uuid,
        public \DateTimeImmutable $startedAt,
    ) {}
}

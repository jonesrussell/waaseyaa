<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Handle;

final readonly class SpanHandle
{
    public function __construct(
        public string $uuid,
        public string $traceUuid,
        public string $kind,
        public \DateTimeImmutable $startedAt,
        public ?string $parentSpanUuid = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Event;

final readonly class ToolCallStarted
{
    public function __construct(
        public string $traceUuid,
        public string $callId,
        public string $toolName,
    ) {}
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Event;

final readonly class ToolCallCompleted
{
    public function __construct(
        public string $callId,
        public ?string $error = null,
    ) {}
}

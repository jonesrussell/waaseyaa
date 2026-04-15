<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Event;

final readonly class LlmCallCompleted
{
    public function __construct(
        public string $traceUuid,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedTokens = 0,
    ) {}
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Value;

final readonly class Anomaly
{
    public const KIND_SPAN_COUNT = 'span_count_outlier';
    public const KIND_COST = 'cost_outlier';
    public const KIND_TOOL_LOOP = 'tool_loop';
    public const KIND_ERROR_RATIO = 'high_error_ratio';

    /** @param array<string, mixed> $evidence */
    public function __construct(
        public string $kind,
        public string $description,
        public array $evidence = [],
    ) {}
}

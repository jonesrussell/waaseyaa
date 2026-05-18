<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Event;

/**
 * Domain event emitted after each provider call returns.
 *
 * Carries the token counters and `{provider, model}` pair so the
 * telemetry aggregator can fold them into the per-run record and
 * compute incremental cost via {@see \Waaseyaa\AI\Observability\Pricing\ModelPriceTable}.
 *
 * @api
 */
final readonly class AgentRunProviderCallCompleted
{
    public function __construct(
        public string $runId,
        public string $provider,
        public string $model,
        public int $tokensIn,
        public int $tokensOut,
    ) {}
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Cost;

use Waaseyaa\AI\Observability\Handle\TraceHandle;
use Waaseyaa\AI\Observability\Recorder\TraceRecorderInterface;
use Waaseyaa\AI\Observability\Value\CostRecord;

final class TokenAccountant
{
    public function __construct(
        private readonly TraceRecorderInterface $recorder,
        private readonly ModelPricing $pricing,
    ) {}

    public function record(
        TraceHandle $handle,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cachedTokens = 0,
    ): CostRecord {
        $rates = $this->pricing->forModel($model);
        $cost = ($inputTokens * $rates['input']
            + $outputTokens * $rates['output']
            + $cachedTokens * $rates['cached']) / 1_000_000;

        $record = new CostRecord($model, $inputTokens, $outputTokens, $cachedTokens, $cost);

        $span = $this->recorder->span($handle, 'llm_call', $model);
        $this->recorder->endSpan($span, [
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => $cachedTokens,
            'cost_usd' => $cost,
        ]);

        return $record;
    }
}

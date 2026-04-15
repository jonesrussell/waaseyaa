<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Cost;

/**
 * Per-model USD rates per 1,000,000 tokens.
 * Callers can override defaults via constructor.
 */
final class ModelPricing
{
    /** @var array<string, array{input: float, output: float, cached: float}> */
    private array $rates;

    /** @param array<string, array{input: float, output: float, cached: float}> $overrides */
    public function __construct(array $overrides = [])
    {
        $this->rates = array_replace(self::defaults(), $overrides);
    }

    /** @return array{input: float, output: float, cached: float} */
    public function forModel(string $model): array
    {
        return $this->rates[$model] ?? ['input' => 0.0, 'output' => 0.0, 'cached' => 0.0];
    }

    /** @return array<string, array{input: float, output: float, cached: float}> */
    private static function defaults(): array
    {
        return [
            'claude-opus-4-6' => ['input' => 15.00, 'output' => 75.00, 'cached' => 1.50],
            'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00, 'cached' => 0.30],
            'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cached' => 0.10],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00, 'cached' => 1.25],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60, 'cached' => 0.075],
        ];
    }
}

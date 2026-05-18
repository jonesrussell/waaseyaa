<?php

declare(strict_types=1);

/*
 * Static price table; update via PR. Sourced from each provider's public
 * pricing page. Mismatched models return NULL (downstream nil-safe).
 *
 * All values are expressed in **cents-per-million-tokens** so all cost
 * arithmetic stays in integer math (no floating-point drift).
 */

namespace Waaseyaa\AI\Observability\Pricing;

/**
 * Maps `{provider, model}` pairs to integer input/output cost rates
 * for the agent-executor telemetry pipeline (FR-029).
 *
 * Rates are stored as **cents per million tokens** so that the
 * `priceCentsFor()` arithmetic remains integer-only:
 *
 *     cost = floor((tokensIn * inputRate + tokensOut * outputRate) / 1_000_000)
 *
 * Unknown `"{provider}:{model}"` keys return `null`; consumers must
 * be nil-safe (see {@see \Waaseyaa\AI\Observability\Listener\AgentRunTelemetryListener}).
 *
 * @api
 */
final class ModelPriceTable
{
    /**
     * Price table keyed by `"{provider}:{model}"`.
     *
     * Values are integer cents-per-million-tokens. Update by editing
     * this constant and shipping a PR; downstream is nil-safe for any
     * unknown key.
     *
     * @var array<string, array{input_per_million: int, output_per_million: int}>
     */
    private const PRICES = [
        // Anthropic — public pricing page, USD per 1M tokens × 100 cents.
        'anthropic:claude-sonnet-4-6' => ['input_per_million' => 300, 'output_per_million' => 1500],
        'anthropic:claude-opus-4-7' => ['input_per_million' => 1500, 'output_per_million' => 7500],
        'anthropic:claude-haiku-4-5' => ['input_per_million' => 100, 'output_per_million' => 500],

        // OpenAI — public pricing page.
        'openai:gpt-4o' => ['input_per_million' => 250, 'output_per_million' => 1000],
        'openai:gpt-4o-mini' => ['input_per_million' => 15, 'output_per_million' => 60],

        // Null provider — zero-cost sentinel for tests / dry runs.
        'null:null' => ['input_per_million' => 0, 'output_per_million' => 0],
    ];

    /**
     * Compute the integer cost in cents for a `{provider, model}` pair.
     *
     * Returns NULL when the provider:model key is not in the table — the
     * caller MUST be nil-safe (telemetry persistence stores NULL for
     * unknown models per data-model semantics).
     *
     * @param string $providerModel `"{provider}:{model}"` (lower-case).
     * @param int    $tokensIn      Input tokens consumed (>= 0).
     * @param int    $tokensOut     Output tokens produced (>= 0).
     */
    public function priceCentsFor(string $providerModel, int $tokensIn, int $tokensOut): ?int
    {
        if (!isset(self::PRICES[$providerModel])) {
            return null;
        }

        if ($tokensIn < 0 || $tokensOut < 0) {
            return null;
        }

        $rate = self::PRICES[$providerModel];

        // Integer arithmetic: (tokens × cents-per-million) / 1_000_000.
        // intdiv truncates toward zero — predictable, no float drift.
        return \intdiv(
            $tokensIn * $rate['input_per_million'] + $tokensOut * $rate['output_per_million'],
            1_000_000,
        );
    }

    /**
     * Return the list of known `"{provider}:{model}"` keys.
     *
     * Useful for diagnostics / `config:show pricing` style commands.
     *
     * @return list<string>
     */
    public function knownModels(): array
    {
        return \array_keys(self::PRICES);
    }
}

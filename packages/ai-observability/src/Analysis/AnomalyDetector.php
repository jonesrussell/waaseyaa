<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Analysis;

use Waaseyaa\AI\Observability\Value\Anomaly;

/**
 * @api
 */
final class AnomalyDetector
{
    private const TOOL_LOOP_THRESHOLD = 10;
    private const ERROR_RATIO_THRESHOLD = 0.5;
    private const SPAN_COUNT_SIGMA = 3.0;
    private const COST_OUTLIER_MULTIPLIER = 2.0;

    /**
     * @param array<int, array{kind: string, name: string, status: string, cost_usd?: float}> $spans
     * @param array<int, array{span_count: int, cost_usd: float}> $history
     * @return Anomaly[]
     */
    public function check(string $traceLabel, array $spans, array $history): array
    {
        $anomalies = [];

        if (($loop = $this->detectToolLoop($spans)) !== null) {
            $anomalies[] = $loop;
        }
        if (($err = $this->detectErrorRatio($spans)) !== null) {
            $anomalies[] = $err;
        }
        if (($count = $this->detectSpanCountOutlier(count($spans), $history)) !== null) {
            $anomalies[] = $count;
        }
        if (($cost = $this->detectCostOutlier($this->sumCost($spans), $history)) !== null) {
            $anomalies[] = $cost;
        }

        return $anomalies;
    }

    /** @param array<int, array{kind: string, name: string}> $spans */
    private function detectToolLoop(array $spans): ?Anomaly
    {
        $counts = [];
        foreach ($spans as $s) {
            if ($s['kind'] === 'tool_call') {
                $counts[$s['name']] = ($counts[$s['name']] ?? 0) + 1;
            }
        }
        foreach ($counts as $name => $n) {
            if ($n > self::TOOL_LOOP_THRESHOLD) {
                return new Anomaly(
                    Anomaly::KIND_TOOL_LOOP,
                    sprintf('Tool "%s" called %d times in one trace', $name, $n),
                    ['tool' => $name, 'call_count' => $n],
                );
            }
        }

        return null;
    }

    /** @param array<int, array{status: string}> $spans */
    private function detectErrorRatio(array $spans): ?Anomaly
    {
        if ($spans === []) {
            return null;
        }
        $errors = 0;
        foreach ($spans as $s) {
            if ($s['status'] === 'error') {
                $errors++;
            }
        }
        $total = count($spans);
        $ratio = $errors / $total;
        if ($ratio >= self::ERROR_RATIO_THRESHOLD) {
            return new Anomaly(
                Anomaly::KIND_ERROR_RATIO,
                sprintf('%.0f%% of spans are errors (%d/%d)', $ratio * 100, $errors, $total),
                ['error_ratio' => $ratio, 'error_count' => $errors, 'total_spans' => $total],
            );
        }

        return null;
    }

    /** @param array<int, array{span_count: int}> $history */
    private function detectSpanCountOutlier(int $actual, array $history): ?Anomaly
    {
        if (count($history) < 5) {
            return null;
        }
        $counts = array_column($history, 'span_count');
        $mean = array_sum($counts) / count($counts);
        $variance = array_sum(array_map(fn($n) => ($n - $mean) ** 2, $counts)) / count($counts);
        $sigma = sqrt($variance);
        $threshold = $mean + self::SPAN_COUNT_SIGMA * $sigma;

        if ($sigma > 0.0 && $actual > $threshold) {
            return new Anomaly(
                Anomaly::KIND_SPAN_COUNT,
                sprintf('Span count %d exceeds mean+3σ (%.1f)', $actual, $threshold),
                ['actual' => $actual, 'mean' => $mean, 'sigma' => $sigma],
            );
        }

        return null;
    }

    /** @param array<int, array{cost_usd: float}> $history */
    private function detectCostOutlier(float $actual, array $history): ?Anomaly
    {
        if (count($history) < 3 || $actual <= 0.0) {
            return null;
        }
        $costs = array_column($history, 'cost_usd');
        sort($costs);
        $median = $costs[(int) floor(count($costs) / 2)];
        if ($median > 0.0 && $actual > $median * self::COST_OUTLIER_MULTIPLIER) {
            return new Anomaly(
                Anomaly::KIND_COST,
                sprintf('Cost $%.4f is %.1fx median $%.4f', $actual, $actual / $median, $median),
                ['actual' => $actual, 'median' => $median],
            );
        }

        return null;
    }

    /** @param array<int, array{cost_usd?: float}> $spans */
    private function sumCost(array $spans): float
    {
        $total = 0.0;
        foreach ($spans as $s) {
            $total += $s['cost_usd'] ?? 0.0;
        }

        return $total;
    }
}

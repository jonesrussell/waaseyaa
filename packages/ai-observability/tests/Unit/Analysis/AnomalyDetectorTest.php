<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Analysis\AnomalyDetector;
use Waaseyaa\AI\Observability\Value\Anomaly;

#[CoversClass(AnomalyDetector::class)]
final class AnomalyDetectorTest extends TestCase
{
    #[Test]
    public function flagsToolLoopWhenSameToolCalledElevenTimes(): void
    {
        $detector = new AnomalyDetector();
        $spans = array_fill(0, 11, ['kind' => 'tool_call', 'name' => 'grep', 'status' => 'ok']);

        $anomalies = $detector->check('x', $spans, []);

        self::assertContains(Anomaly::KIND_TOOL_LOOP, array_map(fn (Anomaly $a) => $a->kind, $anomalies));
    }

    #[Test]
    public function flagsHighErrorRatio(): void
    {
        $detector = new AnomalyDetector();
        $spans = [
            ['kind' => 'tool_call', 'name' => 'a', 'status' => 'error'],
            ['kind' => 'tool_call', 'name' => 'b', 'status' => 'error'],
            ['kind' => 'tool_call', 'name' => 'c', 'status' => 'ok'],
        ];

        $anomalies = $detector->check('x', $spans, []);

        self::assertContains(Anomaly::KIND_ERROR_RATIO, array_map(fn (Anomaly $a) => $a->kind, $anomalies));
    }

    #[Test]
    public function flagsSpanCountOutlier(): void
    {
        $detector = new AnomalyDetector();
        $history = array_map(fn ($n) => ['span_count' => $n, 'cost_usd' => 0.10], [5, 5, 6, 4, 5, 5, 6, 4, 5, 5]);
        $spans = array_fill(0, 30, ['kind' => 'tool_call', 'name' => 'x', 'status' => 'ok']);

        $anomalies = $detector->check('x', $spans, $history);

        self::assertContains(Anomaly::KIND_SPAN_COUNT, array_map(fn (Anomaly $a) => $a->kind, $anomalies));
    }

    #[Test]
    public function flagsCostOutlier(): void
    {
        $detector = new AnomalyDetector();
        $history = array_map(fn ($c) => ['span_count' => 5, 'cost_usd' => $c], [0.10, 0.12, 0.09, 0.11, 0.10, 0.12, 0.08, 0.10]);
        $spans = [['kind' => 'llm_call', 'name' => 'x', 'status' => 'ok', 'cost_usd' => 1.00]];

        $anomalies = $detector->check('x', $spans, $history);

        self::assertContains(Anomaly::KIND_COST, array_map(fn (Anomaly $a) => $a->kind, $anomalies));
    }

    #[Test]
    public function returnsEmptyForUnremarkableTrace(): void
    {
        $detector = new AnomalyDetector();
        $history = [['span_count' => 5, 'cost_usd' => 0.10]];
        $spans = [['kind' => 'tool_call', 'name' => 'x', 'status' => 'ok']];

        self::assertSame([], $detector->check('x', $spans, $history));
    }
}

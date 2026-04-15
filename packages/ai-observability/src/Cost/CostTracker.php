<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Cost;

use Waaseyaa\Database\DatabaseInterface;

final class CostTracker
{
    public function __construct(private readonly DatabaseInterface $database) {}

    public function totalForTrace(string $traceUuid): float
    {
        $rows = $this->database->select('trace_span', 'ts')
            ->fields('ts', ['attributes'])
            ->condition('trace_uuid', $traceUuid)
            ->condition('kind', 'llm_call')
            ->execute();

        return $this->sumCostFromRows($rows);
    }

    public function totalForPeriod(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        $rows = $this->database->select('trace_span', 'ts')
            ->fields('ts', ['attributes'])
            ->condition('kind', 'llm_call')
            ->condition('started_at', $from->format('Y-m-d H:i:s'), '>=')
            ->condition('started_at', $to->format('Y-m-d H:i:s'), '<=')
            ->execute();

        return $this->sumCostFromRows($rows);
    }

    private function sumCostFromRows(\Traversable $rows): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $raw = $row['attributes'] ?? null;
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            try {
                $attrs = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }
            if (is_array($attrs)) {
                $total += (float) ($attrs['cost_usd'] ?? 0.0);
            }
        }

        return $total;
    }
}

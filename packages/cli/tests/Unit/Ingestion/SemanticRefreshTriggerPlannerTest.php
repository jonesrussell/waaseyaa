<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Ingestion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Ingestion\SemanticRefreshTriggerPlanner;

#[CoversClass(SemanticRefreshTriggerPlanner::class)]
final class SemanticRefreshTriggerPlannerTest extends TestCase
{
    #[Test]
    public function it_prioritizes_provenance_over_other_categories(): void
    {
        $planner = new SemanticRefreshTriggerPlanner();
        $baseline = $this->snapshot('batch_a', 'dataset://a', 'atomic_fail_fast', false);
        $current = $this->snapshot('batch_b', 'dataset://a', 'validate_only', true);

        $plan = $planner->plan($current, $baseline);
        $this->assertTrue($plan['summary']['needs_refresh']);
        $this->assertSame('provenance_change', $plan['summary']['primary_category']);
        $this->assertSame('refresh.provenance_change', $plan['diagnostics'][0]['code']);
    }

    #[Test]
    public function it_emits_relationship_change_when_provenance_is_stable(): void
    {
        $planner = new SemanticRefreshTriggerPlanner();
        $baseline = $this->snapshot('batch_a', 'dataset://a', 'atomic_fail_fast', false, []);
        $current = $this->snapshot('batch_a', 'dataset://a', 'atomic_fail_fast', false, [[
            'key' => 'a_to_b_related_to_inferred',
            'relationship_type' => 'related_to',
            'from' => 'a',
            'to' => 'b',
            'inference_confidence' => 0.44,
        ]]);

        $plan = $planner->plan($current, $baseline);
        $this->assertSame('relationship_change', $plan['summary']['primary_category']);
        $this->assertSame('added', $plan['diagnostics'][0]['context']['change']);
    }

    /**
     * @param list<array<string, mixed>> $relationships
     * @return array<string, mixed>
     */
    private function snapshot(
        string $batchId,
        string $sourceSetUri,
        string $policy,
        bool $infer,
        array $relationships = [],
    ): array {
        return [
            'envelope' => [
                'batch_id' => $batchId,
                'source_set_uri' => $sourceSetUri,
                'items' => [[
                    'source_uri' => 'item://a',
                    'ingested_at' => '1735689600',
                    'parser_version' => null,
                ]],
            ],
            'policy' => [
                'ingestion_policy' => $policy,
                'infer_relationships' => $infer,
            ],
            'relationships' => $relationships,
            'structure' => [
                'item_count' => 1,
                'node_count' => 1,
                'relationship_count' => count($relationships),
                'item_field_types' => [
                    'ingested_at' => 'string_or_int',
                    'parser_version' => 'string_or_null',
                    'source_uri' => 'string',
                ],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Ingestion;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Ingestion\CrossSourceIdentityResolver;
use Waaseyaa\CLI\Ingestion\SemanticRefreshTriggerPlanner;
use Waaseyaa\CLI\Ingestion\SourcePriorityMergeResolver;

final class MultiSourceFixturePackTest extends TestCase
{
    #[Test]
    public function federated_fixture_pack_is_deterministic_across_identity_merge_and_refresh(): void
    {
        $fixture = $this->loadFixture('multi-source-federated.input.json');
        $resolver = new CrossSourceIdentityResolver();
        $merger = new SourcePriorityMergeResolver();
        $planner = new SemanticRefreshTriggerPlanner();

        $firstIdentity = $resolver->resolve($fixture['rows']);
        $secondIdentity = $resolver->resolve(array_reverse($fixture['rows']));
        $this->assertSame($firstIdentity, $secondIdentity);

        $mergeResult = $merger->merge($firstIdentity['rows']);
        $mergeCodes = array_values(array_map(
            static fn(array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $mergeResult['diagnostics'],
        ));
        $this->assertContains('merge.field_conflict', $mergeCodes);

        $baseline = $this->loadFixture('multi-source-refresh.baseline.json');
        $current = $this->loadFixture('multi-source-refresh.current.json');
        $refresh = $planner->plan($current, $baseline);
        $this->assertSame('relationship_change', $refresh['summary']['primary_category']);
        $this->assertSame('refresh.relationship_change', $refresh['diagnostics'][0]['code']);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $name): array
    {
        $path = dirname(__DIR__, 5) . '/tests/fixtures/ingestion/' . $name;
        $raw = file_get_contents($path);
        return json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    }
}

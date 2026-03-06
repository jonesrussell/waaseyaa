<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Ingestion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Ingestion\SourceAdapterNormalizer;

#[CoversClass(SourceAdapterNormalizer::class)]
final class SourceAdapterNormalizerTest extends TestCase
{
    #[Test]
    public function it_normalizes_complex_uri_and_emits_ordered_diagnostic(): void
    {
        $normalizer = new SourceAdapterNormalizer();
        $result = $normalizer->normalize([
            'raw_source_uri' => 'HTTP://Example.COM:80///path//to///Resource/?b=2&a=1&&c=&b=3#fragment',
            'adapter_type' => 'dataset',
            'ownership' => 'first_party',
            'synthetic_flag' => false,
            'batch_id' => 'batch-normalize',
            'ingested_at' => '2026-03-05T17:55:00Z',
            'parser_version' => '1.0.0',
            'metadata' => [
                'adapter_extra' => [
                    'provider_scope' => 'internal',
                    'provider_id' => 'ds-a',
                ],
            ],
        ]);

        $this->assertSame('http://example.com/path/to/Resource?a=1&b=2&b=3', $result['provenance']['source_uri']);
        $this->assertSame('20c2f71168ae5ba0250e3cafbd9ca525c4e5b02d9ded2e1b5c8e595d45484ea0', $result['provenance']['source_id']);
        $this->assertCount(1, $result['diagnostics']);

        $diagnostic = $result['diagnostics'][0];
        $this->assertSame('adapter.normalized_uri', $diagnostic['code']);
        $this->assertSame('Normalized source_uri to canonical form.', $diagnostic['message']);
        $this->assertSame('/source_uri', $diagnostic['location']);
        $this->assertSame([
            'scheme_lowercased',
            'host_lowercased',
            'default_port_stripped',
            'path_collapsed',
            'trailing_slash_removed',
            'query_sorted',
            'empty_query_param_removed',
            'fragment_stripped',
        ], $diagnostic['context']['normalization_steps']);

        $this->assertSame(['provider_id' => 'ds-a', 'provider_scope' => 'internal'], $result['metadata']['adapter_extra']);
    }

    #[Test]
    public function it_matches_the_mutation_fixture(): void
    {
        $fixture = $this->loadFixture('adapter-normalization-mutation.json');
        $normalizer = new SourceAdapterNormalizer();
        $result = $normalizer->normalize($fixture['payload']);

        $this->assertSame($fixture['expected']['provenance'], $result['provenance']);
        $this->assertSame($fixture['expected']['diagnostics'], $result['diagnostics']);
        $this->assertSame($fixture['expected']['metadata'], $result['metadata']);
    }

    #[Test]
    public function it_matches_the_canonical_fixture(): void
    {
        $fixture = $this->loadFixture('adapter-normalization-canonical.json');
        $normalizer = new SourceAdapterNormalizer();
        $result = $normalizer->normalize($fixture['payload']);

        $this->assertSame($fixture['expected']['provenance'], $result['provenance']);
        $this->assertSame([], $result['diagnostics']);
        $this->assertSame($fixture['expected']['metadata'], $result['metadata']);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $name): array
    {
        $path = dirname(__DIR__, 5) . '/tests/fixtures/ingestion/' . $name;
        return json_decode(file_get_contents($path), true);
    }
}

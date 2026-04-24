<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Tests\Unit\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\NorthCloud\Client\NorthCloudClient;
use Waaseyaa\NorthCloud\Search\NorthCloudSearchProvider;
use Waaseyaa\Search\SearchRequest;

/**
 * @covers \Waaseyaa\NorthCloud\Search\NorthCloudSearchProvider
 */
#[CoversClass(NorthCloudSearchProvider::class)]
final class NorthCloudSearchProviderTest extends TestCase
{
    #[Test]
    public function malformedHitTopicsAreIgnoredInsteadOfFatal(): void
    {
        $provider = new NorthCloudSearchProvider(
            client: new NorthCloudClient(
                baseUrl: 'https://nc.test',
                httpClient: static fn(string $url): string => (string) json_encode([
                    'hits' => [[
                        'id' => '1',
                        'title' => 'Hit',
                        'topics' => 'oops',
                    ]],
                    'total_hits' => 1,
                ]),
            ),
            cacheTtl: 0,
        );

        $result = $provider->search(new SearchRequest(query: 'water'));

        $this->assertSame(1, $result->totalHits);
        $this->assertCount(1, $result->hits);
        $this->assertSame([], $result->hits[0]->topics);
    }

    #[Test]
    public function malformedFacetBucketsAreIgnoredInsteadOfFatal(): void
    {
        $provider = new NorthCloudSearchProvider(
            client: new NorthCloudClient(
                baseUrl: 'https://nc.test',
                httpClient: static fn(string $url): string => (string) json_encode([
                    'hits' => [],
                    'facets' => [
                        'topics' => 'oops',
                    ],
                    'total_hits' => 0,
                ]),
            ),
            cacheTtl: 0,
        );

        $result = $provider->search(new SearchRequest(query: 'water'));

        $this->assertSame(0, $result->totalHits);
        $this->assertSame([], $result->facets);
    }
}

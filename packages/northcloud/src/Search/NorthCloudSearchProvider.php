<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Search;

use Waaseyaa\NorthCloud\Client\NorthCloudClient;
use Waaseyaa\Search\FacetBucket;
use Waaseyaa\Search\SearchFacet;
use Waaseyaa\Search\SearchHit;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;
use Waaseyaa\Search\SearchResult;

/**
 * NorthCloud-backed implementation of Waaseyaa\Search\SearchProviderInterface.
 *
 * Delegates HTTP transport to {@see NorthCloudClient}; this class handles the
 * SearchRequest -> params translation, response parsing, and a small in-memory
 * response cache.
 *
 * Set $cacheTtl to 0 to disable the provider-level cache.
 */
final class NorthCloudSearchProvider implements SearchProviderInterface
{
    /** @var array<string, array{result: SearchResult, expires: int}> */
    private array $cache = [];

    public function __construct(
        private readonly NorthCloudClient $client,
        private readonly int $cacheTtl = 300,
    ) {}

    public function search(SearchRequest $request): SearchResult
    {
        $cacheKey = $request->cacheKey();

        if ($this->cacheTtl > 0 && isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if ($cached['expires'] > time()) {
                return $cached['result'];
            }
            unset($this->cache[$cacheKey]);
        }

        $params = $this->buildParams($request);
        $data = $this->client->search($params);

        if ($data === null) {
            return SearchResult::empty();
        }

        $searchResult = $this->parseResponse($data);

        if ($this->cacheTtl > 0) {
            $this->cache[$cacheKey] = [
                'result' => $searchResult,
                'expires' => time() + $this->cacheTtl,
            ];
        }

        return $searchResult;
    }

    /**
     * @return array<string, string|int|list<string>>
     */
    private function buildParams(SearchRequest $request): array
    {
        $params = [
            'q' => $request->query,
            'page' => (string) $request->page,
            'page_size' => (string) $request->pageSize,
            'include_facets' => '1',
            'include_highlights' => '1',
        ];

        if ($request->filters->contentType !== '') {
            $params['content_type'] = $request->filters->contentType;
        }
        if ($request->filters->minQuality > 0) {
            $params['min_quality_score'] = (string) $request->filters->minQuality;
        }
        if ($request->filters->topics !== []) {
            $params['topics'] = array_values(array_map(strval(...), $request->filters->topics));
        }
        if ($request->filters->sourceNames !== []) {
            $params['source_names'] = array_values(array_map(strval(...), $request->filters->sourceNames));
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseResponse(array $data): SearchResult
    {
        $hits = [];
        foreach ($data['hits'] ?? [] as $hit) {
            $hits[] = new SearchHit(
                id: (string) ($hit['id'] ?? ''),
                title: (string) ($hit['title'] ?? ''),
                url: (string) ($hit['url'] ?? ''),
                sourceName: (string) ($hit['source_name'] ?? ''),
                crawledAt: (string) ($hit['crawled_at'] ?? ''),
                qualityScore: (int) ($hit['quality_score'] ?? 0),
                contentType: (string) ($hit['content_type'] ?? ''),
                topics: array_map(strval(...), $hit['topics'] ?? []),
                score: (float) ($hit['score'] ?? 0.0),
                ogImage: (string) ($hit['og_image'] ?? ''),
                highlight: $this->extractHighlight($hit['highlight'] ?? ''),
            );
        }

        $facets = [];
        foreach ($data['facets'] ?? [] as $name => $bucketList) {
            $buckets = [];
            foreach ($bucketList as $bucket) {
                $buckets[] = new FacetBucket(
                    key: (string) ($bucket['key'] ?? ''),
                    count: (int) ($bucket['count'] ?? 0),
                );
            }
            $facets[] = new SearchFacet(name: (string) $name, buckets: $buckets);
        }

        return new SearchResult(
            totalHits: (int) ($data['total_hits'] ?? 0),
            totalPages: (int) ($data['total_pages'] ?? 0),
            currentPage: (int) ($data['current_page'] ?? 1),
            pageSize: (int) ($data['page_size'] ?? 20),
            tookMs: (int) ($data['took_ms'] ?? 0),
            hits: $hits,
            facets: $facets,
        );
    }

    private function extractHighlight(mixed $highlight): string
    {
        if (is_string($highlight)) {
            return $highlight;
        }

        if (!is_array($highlight)) {
            return '';
        }

        // API returns {body: [...], raw_text: [...], title: [...]} — prefer body, then raw_text.
        foreach (['body', 'raw_text', 'title'] as $field) {
            if (isset($highlight[$field][0]) && is_string($highlight[$field][0])) {
                return $highlight[$field][0];
            }
        }

        return '';
    }
}

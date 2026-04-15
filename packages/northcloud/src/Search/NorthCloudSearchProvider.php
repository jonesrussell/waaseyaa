<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Search;

use Waaseyaa\Search\FacetBucket;
use Waaseyaa\Search\SearchFacet;
use Waaseyaa\Search\SearchHit;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;
use Waaseyaa\Search\SearchResult;

/**
 * NorthCloud-backed implementation of Waaseyaa\Search\SearchProviderInterface.
 *
 * Apps register this as the active search provider to route search through NC.
 * In-memory response cache with configurable TTL — set to 0 to disable.
 */
final class NorthCloudSearchProvider implements SearchProviderInterface
{
    /** @var array<string, array{result: SearchResult, expires: int}> */
    private array $cache = [];

    /** @var \Closure|null */
    private readonly ?\Closure $httpClient;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 5,
        private readonly int $cacheTtl = 300,
        ?callable $httpClient = null,
    ) {
        $this->httpClient = $httpClient !== null ? $httpClient(...) : null;
    }

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

        $url = $this->buildQueryUrl($request);
        $json = $this->doRequest($url);

        if ($json === false) {
            return SearchResult::empty();
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
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

    private function buildQueryUrl(SearchRequest $request): string
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

        $url = rtrim($this->baseUrl, '/') . '/api/v1/search?' . http_build_query($params);

        // Array params need explicit bracket notation for Go's query parser.
        foreach ($request->filters->topics as $topic) {
            $url .= '&' . urlencode('topics[]') . '=' . urlencode($topic);
        }
        foreach ($request->filters->sourceNames as $source) {
            $url .= '&' . urlencode('source_names[]') . '=' . urlencode($source);
        }

        return $url;
    }

    private function doRequest(string $url): string|false
    {
        if ($this->httpClient !== null) {
            return ($this->httpClient)($url);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            error_log(sprintf('NorthCloud search request failed: %s', $url));
        }

        return $result;
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

<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Client;

/**
 * HTTP client for the North Cloud REST API.
 *
 * Read endpoints (search, people, band office, dictionary) are unauthenticated.
 * Write endpoints (crawl jobs, link-sources) require a bearer token.
 *
 * Pass an optional callable $httpClient for testing; the default uses file_get_contents
 * with a stream context so the package has no external HTTP client dependency.
 */
final class NorthCloudClient
{
    /** Attribution string matching the NC API X-Attribution header. */
    public const string DICTIONARY_ATTRIBUTION = "Ojibwe People's Dictionary, University of Minnesota";

    /** @var \Closure|null */
    private readonly ?\Closure $httpClient;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 5,
        ?callable $httpClient = null,
        private readonly ?NorthCloudCache $cache = null,
        private readonly string $apiToken = '',
    ) {
        $this->httpClient = $httpClient !== null ? $httpClient(...) : null;
    }

    /**
     * Fetch current leadership for a community.
     *
     * @return list<array{id: string, name: string, role: string, role_title?: string, email?: string, phone?: string, verified: bool}>|null
     */
    public function getPeople(string $ncId): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/communities/' . urlencode($ncId) . '/people?current_only=true';
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['people']) || !is_array($data['people'])) {
            error_log(sprintf('NorthCloud people response malformed for community %s', $ncId));
            return null;
        }

        return $data['people'];
    }

    /**
     * Fetch band office contact info for a community.
     *
     * @return array{address_line1?: string, address_line2?: string, city?: string, province?: string, postal_code?: string, phone?: string, fax?: string, email?: string, toll_free?: string, office_hours?: string, verified: bool}|null
     */
    public function getBandOffice(string $ncId): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/communities/' . urlencode($ncId) . '/band-office';
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['band_office']) || !is_array($data['band_office'])) {
            return null;
        }

        return $data['band_office'];
    }

    /**
     * Fetch paginated dictionary entries from NorthCloud.
     *
     * @return array{entries: list<array<string, mixed>>, total: int, attribution: string}|null
     */
    public function getDictionaryEntries(int $page = 1, int $limit = 50): ?array
    {
        $offset = ($page - 1) * $limit;
        $url = rtrim($this->baseUrl, '/') . '/api/v1/dictionary/entries?limit=' . $limit . '&offset=' . $offset;
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
            error_log('NorthCloud dictionary entries response malformed');
            return null;
        }

        return [
            'entries' => $data['entries'],
            'total' => (int) ($data['total'] ?? 0),
            'attribution' => self::DICTIONARY_ATTRIBUTION,
        ];
    }

    /**
     * Search dictionary entries via NorthCloud full-text search.
     *
     * @return array{entries: list<array<string, mixed>>, total: int, attribution: string}|null
     */
    public function searchDictionary(string $query): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/dictionary/search?q=' . urlencode($query);
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
            error_log(sprintf('NorthCloud dictionary search response malformed for query: %s', $query));
            return null;
        }

        return [
            'entries' => $data['entries'],
            'total' => (int) ($data['total'] ?? 0),
            'attribution' => self::DICTIONARY_ATTRIBUTION,
        ];
    }

    /**
     * Fetch recent content from NorthCloud Search API.
     *
     * @param int $limit Maximum results
     * @param string|null $since ISO date (YYYY-MM-DD) to fetch content from
     * @param string $searchQuery Optional text query (empty by default)
     * @param list<string> $topics Topic filters (default: ['indigenous'])
     * @param int $minQuality Minimum quality score (default: 60)
     * @return array{hits: list<array<string, mixed>>, total_hits: int}|null
     */
    public function getRecentContent(int $limit = 20, ?string $since = null, string $searchQuery = '', array $topics = ['indigenous'], int $minQuality = 60): ?array
    {
        $query = 'size=' . $limit . '&min_quality=' . $minQuality;
        if ($searchQuery !== '') {
            $query .= '&q=' . urlencode($searchQuery);
        }
        foreach ($topics as $topic) {
            $query .= '&topics[]=' . urlencode($topic);
        }
        if ($since !== null) {
            $query .= '&from_date=' . urlencode($since);
        }

        $url = rtrim($this->baseUrl, '/') . '/api/v1/search?' . $query;
        $json = $this->doRequest($url);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['hits']) || !is_array($data['hits'])) {
            error_log('NorthCloud search response malformed');
            return null;
        }

        return [
            'hits' => $data['hits'],
            'total_hits' => (int) ($data['total_hits'] ?? 0),
        ];
    }

    /**
     * Link community sources via NorthCloud API.
     *
     * Requires authentication (api_token).
     *
     * @return array<string, mixed>|null
     */
    public function linkSources(bool $dryRun = true): ?array
    {
        $dryRunParam = $dryRun ? 'true' : 'false';
        $url = rtrim($this->baseUrl, '/') . '/api/v1/communities/link-sources?dry_run=' . $dryRunParam;
        $json = $this->doAuthenticatedRequest($url, 'POST');

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            error_log('NorthCloud link-sources response malformed');
            return null;
        }

        return $data;
    }

    /**
     * Create a leadership scrape job for a community.
     *
     * Requires authentication (api_token).
     *
     * @return array<string, mixed>|null
     */
    public function createLeadershipScrapeJob(string $ncId): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/api/v1/crawl/jobs';
        $body = json_encode([
            'community_id' => $ncId,
            'job_type' => 'leadership_scrape',
        ], JSON_THROW_ON_ERROR);

        $json = $this->doAuthenticatedRequest($url, 'POST', $body);

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            error_log(sprintf('NorthCloud create crawl job response malformed for community %s', $ncId));
            return null;
        }

        return $data;
    }

    private function doRequest(string $url): ?string
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get($url);
            if ($cached !== null) {
                return $cached;
            }
        }

        if ($this->httpClient !== null) {
            $result = ($this->httpClient)($url);
            $result = $result === false ? null : $result;
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                ],
            ]);

            $result = @file_get_contents($url, false, $context);
            if ($result === false) {
                error_log(sprintf('NorthCloud API request failed: %s', $url));
                $result = null;
            }
        }

        if ($result !== null && $this->cache !== null) {
            $this->cache->set($url, $result);
        }

        return $result;
    }

    private function doAuthenticatedRequest(string $url, string $method = 'POST', ?string $body = null): ?string
    {
        if ($this->httpClient !== null) {
            $result = ($this->httpClient)($url, $method, $body);
            return $result === false ? null : $result;
        }

        if ($this->apiToken === '') {
            error_log('NorthCloud API token not configured — cannot make authenticated request');
            return null;
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
        ];

        $httpOptions = [
            'method' => $method,
            'timeout' => $this->timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ];

        if ($body !== null) {
            $httpOptions['content'] = $body;
        }

        $context = stream_context_create(['http' => $httpOptions]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            error_log(sprintf('NorthCloud authenticated API request failed: %s', $url));
            return null;
        }

        return $result;
    }
}

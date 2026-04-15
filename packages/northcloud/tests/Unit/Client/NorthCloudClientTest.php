<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\NorthCloud\Client\NorthCloudClient;

#[CoversClass(NorthCloudClient::class)]
final class NorthCloudClientTest extends TestCase
{
    #[Test]
    public function getRecentContentReturnsHitsAndTotal(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static fn(string $url): string => (string) json_encode([
                'hits' => [['title' => 'Hit 1'], ['title' => 'Hit 2']],
                'total_hits' => 2,
            ]),
        );

        $result = $client->getRecentContent();

        $this->assertNotNull($result);
        $this->assertCount(2, $result['hits']);
        $this->assertSame(2, $result['total_hits']);
    }

    #[Test]
    public function getRecentContentReturnsNullOnMalformedResponse(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static fn(string $url): string => '{"not_hits": []}',
        );

        $this->assertNull($client->getRecentContent());
    }

    #[Test]
    public function getRecentContentBuildsUrlWithTopicsAndSince(): void
    {
        $capturedUrl = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static function (string $url) use (&$capturedUrl): string {
                $capturedUrl = $url;
                return (string) json_encode(['hits' => [], 'total_hits' => 0]);
            },
        );

        $client->getRecentContent(limit: 10, since: '2026-01-01', topics: ['indigenous', 'governance']);

        $this->assertStringContainsString('size=10', $capturedUrl);
        $this->assertStringContainsString('topics[]=indigenous', $capturedUrl);
        $this->assertStringContainsString('topics[]=governance', $capturedUrl);
        $this->assertStringContainsString('from_date=2026-01-01', $capturedUrl);
    }

    #[Test]
    public function getPeopleReturnsPeopleArray(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static fn(string $url): string => (string) json_encode([
                'people' => [
                    ['id' => '1', 'name' => 'Chief Test', 'role' => 'chief', 'verified' => true],
                ],
            ]),
        );

        $people = $client->getPeople('nc-community-123');

        $this->assertNotNull($people);
        $this->assertCount(1, $people);
        $this->assertSame('Chief Test', $people[0]['name']);
    }

    #[Test]
    public function searchDictionaryReturnsEntriesAndAttribution(): void
    {
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static fn(string $url): string => (string) json_encode([
                'entries' => [['word' => 'aanii']],
                'total' => 1,
            ]),
        );

        $result = $client->searchDictionary('aanii');

        $this->assertNotNull($result);
        $this->assertCount(1, $result['entries']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(NorthCloudClient::DICTIONARY_ATTRIBUTION, $result['attribution']);
    }

    #[Test]
    public function unauthenticatedWriteCallReturnsNullWhenTokenMissing(): void
    {
        $client = new NorthCloudClient(baseUrl: 'https://nc.test');

        // No custom httpClient — forces the real authenticated path, which bails when token is empty.
        $this->assertNull($client->linkSources());
    }

    #[Test]
    public function authenticatedCallUsesInjectedHttpClient(): void
    {
        $capturedMethod = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static function (string $url, string $method = 'GET', ?string $body = null, array $headers = []) use (&$capturedMethod): string {
                $capturedMethod = $method;
                return (string) json_encode(['ok' => true]);
            },
            apiToken: 'secret',
        );

        $result = $client->linkSources(dryRun: false);

        $this->assertSame(['ok' => true], $result);
        $this->assertSame('POST', $capturedMethod);
    }

    #[Test]
    public function authenticatedCallPassesBearerHeaderToInjectedClient(): void
    {
        $capturedHeaders = [];
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static function (string $url, string $method = 'GET', ?string $body = null, array $headers = []) use (&$capturedHeaders): string {
                $capturedHeaders = $headers;
                return (string) json_encode(['ok' => true]);
            },
            apiToken: 'secret-token',
        );

        $client->linkSources(dryRun: false);

        $this->assertContains('Authorization: Bearer secret-token', $capturedHeaders);
        $this->assertContains('Content-Type: application/json', $capturedHeaders);
    }

    #[Test]
    public function authenticatedCallReturnsNullWhenTokenEmptyEvenWithInjectedClient(): void
    {
        $called = false;
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static function () use (&$called): string {
                $called = true;
                return '{"ok": true}';
            },
        );

        $this->assertNull($client->linkSources());
        $this->assertFalse($called, 'Injected client should not be called when token is empty');
    }

    #[Test]
    public function constructorRejectsHttpBaseUrlByDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NorthCloudClient(baseUrl: 'http://nc.test');
    }

    #[Test]
    public function constructorAcceptsHttpsBaseUrl(): void
    {
        $client = new NorthCloudClient(baseUrl: 'https://nc.test');
        $this->assertInstanceOf(NorthCloudClient::class, $client);
    }

    #[Test]
    public function constructorAcceptsHttpBaseUrlWhenAllowInsecure(): void
    {
        $client = new NorthCloudClient(baseUrl: 'http://nc.test', allowInsecure: true);
        $this->assertInstanceOf(NorthCloudClient::class, $client);
    }

    #[Test]
    public function searchMethodBuildsArrayParamsAndReturnsDecodedResponse(): void
    {
        $capturedUrl = '';
        $client = new NorthCloudClient(
            baseUrl: 'https://nc.test',
            httpClient: static function (string $url) use (&$capturedUrl): string {
                $capturedUrl = $url;
                return (string) json_encode(['hits' => [['id' => 'x']], 'total_hits' => 1]);
            },
        );

        $result = $client->search([
            'q' => 'water',
            'page' => 1,
            'topics' => ['indigenous', 'governance'],
        ]);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['total_hits']);
        $this->assertStringContainsString('q=water', $capturedUrl);
        $this->assertStringContainsString('page=1', $capturedUrl);
        $this->assertStringContainsString('topics%5B%5D=indigenous', $capturedUrl);
        $this->assertStringContainsString('topics%5B%5D=governance', $capturedUrl);
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Api\Tests\Unit;

use Aurora\Api\JsonApiDocument;
use Aurora\Api\JsonApiError;
use Aurora\Api\JsonApiResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonApiDocument::class)]
final class JsonApiDocumentTest extends TestCase
{
    #[Test]
    public function toArrayAlwaysIncludesJsonApiVersion(): void
    {
        $doc = new JsonApiDocument();
        $array = $doc->toArray();

        $this->assertArrayHasKey('jsonapi', $array);
        $this->assertSame(['version' => '1.1'], $array['jsonapi']);
    }

    #[Test]
    public function singleResourceDocument(): void
    {
        $resource = new JsonApiResource(
            type: 'article',
            id: '1',
            attributes: ['title' => 'Hello'],
        );

        $doc = JsonApiDocument::fromResource($resource);
        $array = $doc->toArray();

        $this->assertSame('1.1', $array['jsonapi']['version']);
        $this->assertSame('article', $array['data']['type']);
        $this->assertSame('1', $array['data']['id']);
        $this->assertSame(['title' => 'Hello'], $array['data']['attributes']);
        $this->assertArrayNotHasKey('errors', $array);
    }

    #[Test]
    public function collectionDocument(): void
    {
        $resources = [
            new JsonApiResource(type: 'article', id: '1', attributes: ['title' => 'First']),
            new JsonApiResource(type: 'article', id: '2', attributes: ['title' => 'Second']),
        ];

        $doc = JsonApiDocument::fromCollection($resources);
        $array = $doc->toArray();

        $this->assertIsArray($array['data']);
        $this->assertCount(2, $array['data']);
        $this->assertSame('1', $array['data'][0]['id']);
        $this->assertSame('2', $array['data'][1]['id']);
    }

    #[Test]
    public function emptyCollectionDocument(): void
    {
        $doc = JsonApiDocument::fromCollection([]);
        $array = $doc->toArray();

        $this->assertSame([], $array['data']);
    }

    #[Test]
    public function errorDocument(): void
    {
        $errors = [
            new JsonApiError(status: '404', title: 'Not Found', detail: 'Resource not found.'),
            new JsonApiError(status: '422', title: 'Validation Error', detail: 'Title is required.'),
        ];

        $doc = JsonApiDocument::fromErrors($errors);
        $array = $doc->toArray();

        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayNotHasKey('data', $array);
        $this->assertCount(2, $array['errors']);
        $this->assertSame('404', $array['errors'][0]['status']);
        $this->assertSame('422', $array['errors'][1]['status']);
    }

    #[Test]
    public function errorAndDataDoNotCoexist(): void
    {
        // When errors are present, data should not appear.
        $doc = new JsonApiDocument(
            data: new JsonApiResource(type: 'article', id: '1'),
            errors: [new JsonApiError(status: '500', title: 'Error')],
        );

        $array = $doc->toArray();

        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayNotHasKey('data', $array);
    }

    #[Test]
    public function nullDataDocument(): void
    {
        $doc = JsonApiDocument::empty();
        $array = $doc->toArray();

        $this->assertNull($array['data']);
        $this->assertArrayNotHasKey('errors', $array);
    }

    #[Test]
    public function documentWithLinks(): void
    {
        $doc = JsonApiDocument::fromCollection(
            [],
            links: ['self' => '/api/article', 'next' => '/api/article?page=2'],
        );
        $array = $doc->toArray();

        $this->assertSame('/api/article', $array['links']['self']);
        $this->assertSame('/api/article?page=2', $array['links']['next']);
    }

    #[Test]
    public function documentWithMeta(): void
    {
        $doc = JsonApiDocument::fromCollection(
            [],
            meta: ['total' => 42, 'page' => 1],
        );
        $array = $doc->toArray();

        $this->assertSame(42, $array['meta']['total']);
        $this->assertSame(1, $array['meta']['page']);
    }

    #[Test]
    public function documentWithIncluded(): void
    {
        $resource = new JsonApiResource(type: 'article', id: '1', attributes: ['title' => 'Test']);
        $included = [
            new JsonApiResource(type: 'user', id: '10', attributes: ['name' => 'Author']),
        ];

        $doc = new JsonApiDocument(
            data: $resource,
            included: $included,
        );
        $array = $doc->toArray();

        $this->assertArrayHasKey('included', $array);
        $this->assertCount(1, $array['included']);
        $this->assertSame('user', $array['included'][0]['type']);
        $this->assertSame('10', $array['included'][0]['id']);
    }

    #[Test]
    public function emptyOptionalFieldsAreOmitted(): void
    {
        $doc = new JsonApiDocument(data: null);
        $array = $doc->toArray();

        $this->assertArrayNotHasKey('meta', $array);
        $this->assertArrayNotHasKey('links', $array);
        $this->assertArrayNotHasKey('included', $array);
    }

    #[Test]
    public function documentWithMetaOnly(): void
    {
        $doc = JsonApiDocument::empty(meta: ['deleted' => true]);
        $array = $doc->toArray();

        $this->assertNull($array['data']);
        $this->assertTrue($array['meta']['deleted']);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(JsonApiDocument::class);
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->isFinal());
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Api\Tests\Unit;

use Aurora\Api\JsonApiResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonApiResource::class)]
final class JsonApiResourceTest extends TestCase
{
    #[Test]
    public function constructsWithAllFields(): void
    {
        $resource = new JsonApiResource(
            type: 'article',
            id: '550e8400-e29b-41d4-a716-446655440000',
            attributes: ['title' => 'Hello World', 'body' => 'Content here.'],
            relationships: ['author' => ['data' => ['type' => 'user', 'id' => '1']]],
            links: ['self' => '/api/article/550e8400-e29b-41d4-a716-446655440000'],
            meta: ['created_at' => '2024-01-01'],
        );

        $this->assertSame('article', $resource->type);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $resource->id);
        $this->assertSame(['title' => 'Hello World', 'body' => 'Content here.'], $resource->attributes);
        $this->assertSame(['author' => ['data' => ['type' => 'user', 'id' => '1']]], $resource->relationships);
        $this->assertSame(['self' => '/api/article/550e8400-e29b-41d4-a716-446655440000'], $resource->links);
        $this->assertSame(['created_at' => '2024-01-01'], $resource->meta);
    }

    #[Test]
    public function constructsWithMinimalFields(): void
    {
        $resource = new JsonApiResource(type: 'user', id: '42');

        $this->assertSame('user', $resource->type);
        $this->assertSame('42', $resource->id);
        $this->assertSame([], $resource->attributes);
        $this->assertSame([], $resource->relationships);
        $this->assertSame([], $resource->links);
        $this->assertSame([], $resource->meta);
    }

    #[Test]
    public function toArrayWithMinimalFields(): void
    {
        $resource = new JsonApiResource(type: 'node', id: '1');
        $array = $resource->toArray();

        $expected = [
            'type' => 'node',
            'id' => '1',
        ];

        $this->assertSame($expected, $array);
    }

    #[Test]
    public function toArrayWithAllFields(): void
    {
        $resource = new JsonApiResource(
            type: 'article',
            id: 'abc-123',
            attributes: ['title' => 'Test'],
            relationships: ['tags' => ['data' => []]],
            links: ['self' => '/api/article/abc-123'],
            meta: ['version' => 2],
        );

        $array = $resource->toArray();

        $this->assertSame('article', $array['type']);
        $this->assertSame('abc-123', $array['id']);
        $this->assertSame(['title' => 'Test'], $array['attributes']);
        $this->assertSame(['tags' => ['data' => []]], $array['relationships']);
        $this->assertSame(['self' => '/api/article/abc-123'], $array['links']);
        $this->assertSame(['version' => 2], $array['meta']);
    }

    #[Test]
    public function toArrayExcludesEmptyOptionalFields(): void
    {
        $resource = new JsonApiResource(
            type: 'article',
            id: '1',
            attributes: ['title' => 'Test'],
        );

        $array = $resource->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('attributes', $array);
        $this->assertArrayNotHasKey('relationships', $array);
        $this->assertArrayNotHasKey('links', $array);
        $this->assertArrayNotHasKey('meta', $array);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(JsonApiResource::class);
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->isFinal());
    }
}

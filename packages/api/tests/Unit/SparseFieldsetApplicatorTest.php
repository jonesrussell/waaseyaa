<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Api\JsonApiResource;
use Waaseyaa\Api\SparseFieldsetApplicator;

#[CoversClass(SparseFieldsetApplicator::class)]
final class SparseFieldsetApplicatorTest extends TestCase
{
    #[Test]
    public function applyFiltersAttributesAndRelationshipsToAllowedNames(): void
    {
        $resource = new JsonApiResource(
            type: 'article',
            id: '1',
            attributes: ['title' => 'A', 'body' => 'B'],
            relationships: [
                'author' => ['data' => ['type' => 'user', 'id' => '9']],
                'tags' => ['data' => []],
            ],
            links: ['self' => '/api/article/1'],
        );

        $out = SparseFieldsetApplicator::apply($resource, ['title', 'author']);

        $this->assertSame(['title' => 'A'], $out->attributes);
        $this->assertSame([
            'author' => ['data' => ['type' => 'user', 'id' => '9']],
        ], $out->relationships);
        $this->assertSame(['self' => '/api/article/1'], $out->links);
    }

    #[Test]
    public function applyWithOnlyAttributeNamesDropsRelationships(): void
    {
        $resource = new JsonApiResource(
            type: 'article',
            id: '1',
            attributes: ['title' => 'A'],
            relationships: [
                'author' => ['data' => ['type' => 'user', 'id' => '9']],
            ],
        );

        $out = SparseFieldsetApplicator::apply($resource, ['title']);

        $this->assertSame(['title' => 'A'], $out->attributes);
        $this->assertSame([], $out->relationships);
    }

    #[Test]
    public function applyWithOnlyRelationshipNamesDropsAttributes(): void
    {
        $resource = new JsonApiResource(
            type: 'article',
            id: '1',
            attributes: ['title' => 'A'],
            relationships: [
                'author' => ['data' => ['type' => 'user', 'id' => '9']],
            ],
        );

        $out = SparseFieldsetApplicator::apply($resource, ['author']);

        $this->assertSame([], $out->attributes);
        $this->assertSame([
            'author' => ['data' => ['type' => 'user', 'id' => '9']],
        ], $out->relationships);
    }

    #[Test]
    public function applyPreservesTypeAndId(): void
    {
        $resource = new JsonApiResource(
            type: 'article',
            id: 'uuid-here',
            attributes: ['title' => 'A'],
            relationships: [],
        );

        $out = SparseFieldsetApplicator::apply($resource, ['title']);

        $this->assertSame('article', $out->type);
        $this->assertSame('uuid-here', $out->id);
    }
}

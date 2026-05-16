<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\Exception\UnknownListingException;
use Waaseyaa\Listing\ListingDefinition;
use Waaseyaa\Listing\ListingDefinitionRegistry;

#[CoversClass(ListingDefinitionRegistry::class)]
final class ListingDefinitionRegistryTest extends TestCase
{
    #[Test]
    public function getReturnsRegisteredDefinition(): void
    {
        $articles = new ListingDefinition(id: 'articles', entityType: 'node');
        $terms = new ListingDefinition(id: 'terms', entityType: 'taxonomy_term');

        $registry = new ListingDefinitionRegistry([
            'articles' => $articles,
            'terms' => $terms,
        ]);

        self::assertSame($articles, $registry->get('articles'));
        self::assertSame($terms, $registry->get('terms'));
    }

    #[Test]
    public function getThrowsUnknownListingExceptionOnMiss(): void
    {
        $registry = new ListingDefinitionRegistry([
            'articles' => new ListingDefinition(id: 'articles', entityType: 'node'),
        ]);

        try {
            $registry->get('does_not_exist');
            self::fail('Expected UnknownListingException');
        } catch (UnknownListingException $e) {
            self::assertSame('does_not_exist', $e->listingId);
            self::assertStringContainsString('does_not_exist', $e->getMessage());
        }
    }

    #[Test]
    public function getThrowsWithCorrectIdForEmptyRegistry(): void
    {
        $registry = new ListingDefinitionRegistry([]);

        $this->expectException(UnknownListingException::class);

        $registry->get('anything');
    }

    #[Test]
    public function hasReturnsTrueForRegisteredId(): void
    {
        $registry = new ListingDefinitionRegistry([
            'articles' => new ListingDefinition(id: 'articles', entityType: 'node'),
        ]);

        self::assertTrue($registry->has('articles'));
    }

    #[Test]
    public function hasReturnsFalseForMissingId(): void
    {
        $registry = new ListingDefinitionRegistry([
            'articles' => new ListingDefinition(id: 'articles', entityType: 'node'),
        ]);

        self::assertFalse($registry->has('terms'));
    }

    #[Test]
    public function hasReturnsFalseForEmptyRegistry(): void
    {
        $registry = new ListingDefinitionRegistry([]);

        self::assertFalse($registry->has('anything'));
    }

    #[Test]
    public function allReturnsTheUnderlyingMap(): void
    {
        $articles = new ListingDefinition(id: 'articles', entityType: 'node');
        $terms = new ListingDefinition(id: 'terms', entityType: 'taxonomy_term');

        $registry = new ListingDefinitionRegistry([
            'articles' => $articles,
            'terms' => $terms,
        ]);

        $all = $registry->all();

        self::assertCount(2, $all);
        self::assertSame($articles, $all['articles']);
        self::assertSame($terms, $all['terms']);
    }

    #[Test]
    public function allReturnsEmptyArrayWhenEmpty(): void
    {
        $registry = new ListingDefinitionRegistry([]);

        self::assertSame([], $registry->all());
    }

    #[Test]
    public function fromListBuildsIdKeyedRegistry(): void
    {
        $articles = new ListingDefinition(id: 'articles', entityType: 'node');
        $terms = new ListingDefinition(id: 'terms', entityType: 'taxonomy_term');

        $registry = ListingDefinitionRegistry::fromList([$articles, $terms]);

        self::assertSame($articles, $registry->get('articles'));
        self::assertSame($terms, $registry->get('terms'));
        self::assertTrue($registry->has('articles'));
        self::assertTrue($registry->has('terms'));
    }

    #[Test]
    public function fromListWithEmptyArrayProducesEmptyRegistry(): void
    {
        $registry = ListingDefinitionRegistry::fromList([]);

        self::assertSame([], $registry->all());
        self::assertFalse($registry->has('anything'));
    }
}

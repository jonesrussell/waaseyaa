<?php

declare(strict_types=1);

namespace Aurora\AI\Vector\Tests\Unit;

use Aurora\AI\Vector\EntityEmbedding;
use Aurora\AI\Vector\InMemoryVectorStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityEmbedding::class)]
#[CoversClass(InMemoryVectorStore::class)]
final class LanguageAwareVectorTest extends TestCase
{
    private InMemoryVectorStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryVectorStore();
    }

    #[Test]
    public function entityEmbeddingHasLangcodeProperty(): void
    {
        $embedding = new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [0.1, 0.2, 0.3],
            langcode: 'fr',
            metadata: ['title' => 'Article en francais'],
            createdAt: 1700000000,
        );

        $this->assertSame('fr', $embedding->langcode);
    }

    #[Test]
    public function entityEmbeddingLangcodeDefaultsToEmptyString(): void
    {
        $embedding = new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [0.1, 0.2],
        );

        $this->assertSame('', $embedding->langcode);
    }

    #[Test]
    public function storeMultipleLanguagesForSameEntity(): void
    {
        $enEmbedding = new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'en',
        );
        $frEmbedding = new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [0.0, 1.0],
            langcode: 'fr',
        );

        $this->store->store($enEmbedding);
        $this->store->store($frEmbedding);

        // Both embeddings should exist.
        $this->assertTrue($this->store->has('node', 1));

        // Search without langcode should return both.
        $results = $this->store->search([1.0, 0.0]);
        $this->assertCount(2, $results);
    }

    #[Test]
    public function searchFilteredByLangcode(): void
    {
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'en',
        ));
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'fr',
        ));
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 2,
            vector: [0.9, 0.1],
            langcode: 'en',
        ));

        // Search only English embeddings.
        $results = $this->store->search([1.0, 0.0], langcode: 'en');
        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertSame('en', $result->embedding->langcode);
        }
    }

    #[Test]
    public function searchWithLangcodeFallback(): void
    {
        // Only English embedding exists.
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'en',
        ));

        // Search for French first, fallback to English.
        $results = $this->store->search(
            [1.0, 0.0],
            langcode: 'fr',
            fallbackLangcodes: ['en'],
        );

        $this->assertCount(1, $results);
        $this->assertSame('en', $results[0]->embedding->langcode);
    }

    #[Test]
    public function searchFallbackNotUsedWhenPrimaryHasResults(): void
    {
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'fr',
        ));
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 2,
            vector: [0.9, 0.1],
            langcode: 'en',
        ));

        // French exists, so fallback to English should not be used.
        $results = $this->store->search(
            [1.0, 0.0],
            langcode: 'fr',
            fallbackLangcodes: ['en'],
        );

        $this->assertCount(1, $results);
        $this->assertSame('fr', $results[0]->embedding->langcode);
    }

    #[Test]
    public function searchWithMultipleFallbacksTriesInOrder(): void
    {
        // Only German embedding exists.
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'de',
        ));

        // Search for French, fallback to English (no results), then German.
        $results = $this->store->search(
            [1.0, 0.0],
            langcode: 'fr',
            fallbackLangcodes: ['en', 'de'],
        );

        $this->assertCount(1, $results);
        $this->assertSame('de', $results[0]->embedding->langcode);
    }

    #[Test]
    public function searchWithEntityTypeAndLangcodeFilters(): void
    {
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'en',
        ));
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'taxonomy_term',
            entityId: 2,
            vector: [1.0, 0.0],
            langcode: 'en',
        ));
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 3,
            vector: [0.9, 0.1],
            langcode: 'fr',
        ));

        // Filter by entity type AND langcode.
        $results = $this->store->search(
            [1.0, 0.0],
            entityTypeId: 'node',
            langcode: 'en',
        );

        $this->assertCount(1, $results);
        $this->assertSame('node', $results[0]->embedding->entityTypeId);
        $this->assertSame('en', $results[0]->embedding->langcode);
    }

    #[Test]
    public function deleteRemovesAllLanguageVariants(): void
    {
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'en',
        ));
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [0.0, 1.0],
            langcode: 'fr',
        ));

        $this->assertTrue($this->store->has('node', 1));

        $this->store->delete('node', 1);

        $this->assertFalse($this->store->has('node', 1));

        // Search should return nothing.
        $results = $this->store->search([1.0, 0.0]);
        $this->assertCount(0, $results);
    }

    #[Test]
    public function searchEmptyLanguageReturnsNoResults(): void
    {
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'en',
        ));

        // Search for a language that does not exist and no fallbacks.
        $results = $this->store->search(
            [1.0, 0.0],
            langcode: 'ja',
        );

        $this->assertCount(0, $results);
    }

    #[Test]
    public function searchWithoutLangcodeReturnsAllLanguages(): void
    {
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 1,
            vector: [1.0, 0.0],
            langcode: 'en',
        ));
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 2,
            vector: [0.9, 0.1],
            langcode: 'fr',
        ));
        $this->store->store(new EntityEmbedding(
            entityTypeId: 'node',
            entityId: 3,
            vector: [0.8, 0.2],
            langcode: 'de',
        ));

        $results = $this->store->search([1.0, 0.0]);

        $this->assertCount(3, $results);
    }
}

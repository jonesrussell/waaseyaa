<?php

declare(strict_types=1);

namespace Waaseyaa\Search\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Search\SearchIndexableInterface;

/**
 * @covers \Waaseyaa\Search\SearchIndexableInterface
 */
final class SearchIndexableInterfaceTest extends TestCase
{
    #[Test]
    public function implementor_returns_document_id(): void
    {
        $item = $this->createIndexable('node:42', ['title' => 'Hello'], ['entity_type' => 'node']);

        $this->assertSame('node:42', $item->getSearchDocumentId());
    }

    #[Test]
    public function implementor_returns_search_document(): void
    {
        $item = $this->createIndexable('node:42', ['title' => 'Hello', 'body' => 'World'], []);

        $this->assertSame(['title' => 'Hello', 'body' => 'World'], $item->toSearchDocument());
    }

    #[Test]
    public function implementor_returns_search_metadata(): void
    {
        $metadata = ['entity_type' => 'node', 'content_type' => 'article', 'topics' => ['php']];
        $item = $this->createIndexable('node:42', [], $metadata);

        $this->assertSame($metadata, $item->toSearchMetadata());
    }

    private function createIndexable(string $id, array $document, array $metadata): SearchIndexableInterface
    {
        return new class ($id, $document, $metadata) implements SearchIndexableInterface {
            public function __construct(
                private readonly string $id,
                private readonly array $document,
                private readonly array $metadata,
            ) {}

            public function getSearchDocumentId(): string { return $this->id; }
            public function toSearchDocument(): array { return $this->document; }
            public function toSearchMetadata(): array { return $this->metadata; }
        };
    }
}

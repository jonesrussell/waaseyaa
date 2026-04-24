<?php

declare(strict_types=1);

namespace Waaseyaa\Search\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Search\EventSubscriber\SearchIndexSubscriber;
use Waaseyaa\Search\SearchIndexableInterface;
use Waaseyaa\Search\SearchIndexerInterface;

/**
 * @covers \Waaseyaa\Search\EventSubscriber\SearchIndexSubscriber
 */
#[CoversClass(SearchIndexSubscriber::class)]
final class SearchIndexSubscriberTest extends TestCase
{
    #[Test]
    public function it_subscribes_to_post_save_and_post_delete(): void
    {
        $events = SearchIndexSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(EntityEvents::POST_SAVE->value, $events);
        $this->assertArrayHasKey(EntityEvents::POST_DELETE->value, $events);
    }

    #[Test]
    public function it_indexes_on_post_save_when_entity_is_indexable(): void
    {
        $entity = $this->createIndexableEntity('node:1');
        $indexer = $this->createMockIndexer();
        $indexer->expects($this->once())->method('index')->with($entity);

        $subscriber = new SearchIndexSubscriber($indexer);
        $event = new EntityEvent($entity);
        $subscriber->onPostSave($event);
    }

    #[Test]
    public function it_skips_non_indexable_entities_on_save(): void
    {
        $entity = new class (['id' => 1]) extends \Waaseyaa\Entity\EntityBase {
            public function __construct(array $values) {
                parent::__construct($values, 'dummy', ['id' => 'id']);
            }
        };
        $indexer = $this->createMockIndexer();
        $indexer->expects($this->never())->method('index');

        $subscriber = new SearchIndexSubscriber($indexer);
        $event = new EntityEvent($entity);
        $subscriber->onPostSave($event);
    }

    #[Test]
    public function it_removes_on_post_delete_when_entity_is_indexable(): void
    {
        $entity = $this->createIndexableEntity('node:1');
        $indexer = $this->createMockIndexer();
        $indexer->expects($this->once())->method('remove')->with('node:1');

        $subscriber = new SearchIndexSubscriber($indexer);
        $event = new EntityEvent($entity);
        $subscriber->onPostDelete($event);
    }

    #[Test]
    public function it_catches_indexing_errors_without_crashing(): void
    {
        $entity = $this->createIndexableEntity('node:1');
        $indexer = $this->createMockIndexer();
        $indexer->method('index')->willThrowException(new \RuntimeException('DB error'));

        $subscriber = new SearchIndexSubscriber($indexer);
        $event = new EntityEvent($entity);

        // Should not throw — best-effort side effect
        $subscriber->onPostSave($event);
        $this->assertTrue(true); // No exception = pass
    }

    private function createMockIndexer(): SearchIndexerInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(SearchIndexerInterface::class);
    }

    private function createIndexableEntity(string $docId): SearchIndexableInterface&\Waaseyaa\Entity\EntityInterface
    {
        return new class ($docId) extends \Waaseyaa\Entity\EntityBase implements SearchIndexableInterface {
            private string $docId;

            public function __construct(string $docId) {
                parent::__construct(['id' => 1], 'node', ['id' => 'id']);
                $this->docId = $docId;
            }

            public function getSearchDocumentId(): string { return $this->docId; }
            public function toSearchDocument(): array { return ['title' => 'Test', 'body' => 'Content']; }
            public function toSearchMetadata(): array { return ['entity_type' => 'node']; }
        };
    }
}

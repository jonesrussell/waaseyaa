<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Vector\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Vector\EmbeddingProviderInterface;
use Waaseyaa\AI\Vector\EmbeddingStorageInterface;
use Waaseyaa\AI\Vector\SemanticIndexWarmer;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(SemanticIndexWarmer::class)]
final class SemanticIndexWarmerTest extends TestCase
{
    #[Test]
    public function itWarmsDeterministicallyAndRespectsWorkflowVisibility(): void
    {
        $query = new class implements EntityQueryInterface {
            public function condition(string $field, mixed $value, string $operator = '='): static { return $this; }
            public function exists(string $field): static { return $this; }
            public function notExists(string $field): static { return $this; }
            public function sort(string $field, string $direction = 'ASC'): static { return $this; }
            public function range(int $offset, int $limit): static { return $this; }
            public function count(): static { return $this; }
            public function accessCheck(bool $check = true): static { return $this; }
            public function execute(): array { return [3, 1, 2]; }
        };

        $nodeA = new SemanticWarmerEntity(1, 'node', ['title' => 'Anchor', 'status' => 1, 'workflow_state' => 'published']);
        $nodeB = new SemanticWarmerEntity(2, 'node', ['title' => 'Draft', 'status' => 0, 'workflow_state' => 'draft']);
        $nodeC = new SemanticWarmerEntity(3, 'node', ['title' => 'Public', 'status' => 1, 'workflow_state' => 'published']);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')
            ->with([1, 2, 3])
            ->willReturn([1 => $nodeA, 2 => $nodeB, 3 => $nodeC]);

        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('hasDefinition')->with('node')->willReturn(true);
        $manager->method('getStorage')->with('node')->willReturn($storage);

        $provider = $this->createMock(EmbeddingProviderInterface::class);
        $provider->expects($this->exactly(2))
            ->method('embed')
            ->willReturn([0.1, 0.2]);

        $embeddingStorage = $this->createMock(EmbeddingStorageInterface::class);
        $embeddingStorage->expects($this->exactly(2))
            ->method('store')
            ->with(
                'node',
                $this->logicalOr('1', '3'),
                [0.1, 0.2],
            );
        $embeddingStorage->expects($this->once())
            ->method('delete')
            ->with('node', '2');

        $warmer = new SemanticIndexWarmer(
            entityTypeManager: $manager,
            embeddingStorage: $embeddingStorage,
            embeddingProvider: $provider,
        );

        $report = $warmer->warm(['node']);

        $this->assertSame('ok', $report['status']);
        $this->assertSame(['node'], $report['requested_entity_types']);
        $this->assertSame(3, $report['processed_total']);
        $this->assertSame(2, $report['stored_total']);
        $this->assertSame(1, $report['removed_total']);
        $this->assertSame(0, $report['missing_total']);
        $this->assertSame(3, $report['by_type']['node']['candidates']);
    }

    #[Test]
    public function itReturnsSkippedStatusWhenProviderIsMissing(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->expects($this->never())->method('hasDefinition');

        $embeddingStorage = $this->createMock(EmbeddingStorageInterface::class);
        $embeddingStorage->expects($this->never())->method('store');
        $embeddingStorage->expects($this->never())->method('delete');

        $warmer = new SemanticIndexWarmer(
            entityTypeManager: $manager,
            embeddingStorage: $embeddingStorage,
            embeddingProvider: null,
        );

        $report = $warmer->warm(['node']);

        $this->assertSame('skipped_no_provider', $report['status']);
        $this->assertSame(0, $report['processed_total']);
        $this->assertSame(0, $report['stored_total']);
    }
}

final readonly class SemanticWarmerEntity implements EntityInterface
{
    public function __construct(
        private int|string|null $id,
        private string $entityTypeId,
        private array $values,
    ) {}

    public function id(): int|string|null { return $this->id; }
    public function uuid(): string { return 'uuid'; }
    public function label(): string { return (string) ($this->values['title'] ?? ''); }
    public function getEntityTypeId(): string { return $this->entityTypeId; }
    public function bundle(): string { return 'default'; }
    public function isNew(): bool { return false; }
    public function toArray(): array { return $this->values; }
    public function language(): string { return 'en'; }
}

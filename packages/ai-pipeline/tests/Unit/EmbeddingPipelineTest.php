<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Pipeline\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Pipeline\EmbeddingPipeline;
use Waaseyaa\AI\Vector\EmbeddingProviderInterface;
use Waaseyaa\AI\Vector\EmbeddingStorageInterface;
use Waaseyaa\Entity\EntityInterface;

#[CoversClass(EmbeddingPipeline::class)]
final class EmbeddingPipelineTest extends TestCase
{
    #[Test]
    public function extractsConfiguredFieldsAndStoresEmbedding(): void
    {
        $provider = $this->createMock(EmbeddingProviderInterface::class);
        $provider->expects($this->once())
            ->method('embed')
            ->with("Water Is Life\n\nLong form body")
            ->willReturn([0.1, 0.2, 0.3]);

        $storage = $this->createMock(EmbeddingStorageInterface::class);
        $storage->expects($this->once())
            ->method('store')
            ->with('node', '1', [0.1, 0.2, 0.3]);

        $pipeline = new EmbeddingPipeline(
            storage: $storage,
            config: [
                'ai' => [
                    'embedding_fields' => [
                        'node' => ['title', 'body'],
                    ],
                ],
            ],
            provider: $provider,
        );

        $pipeline->processEntity(new PipelineTestEntity(1, 'node', [
            'title' => 'Water Is Life',
            'body' => 'Long form body',
            'summary' => 'Ignored',
        ]));
    }

    #[Test]
    public function noProviderConfiguredSkipsWithoutWritingStorage(): void
    {
        $storage = $this->createMock(EmbeddingStorageInterface::class);
        $storage->expects($this->never())->method('store');

        $pipeline = new EmbeddingPipeline(
            storage: $storage,
            config: ['ai' => []],
        );

        $pipeline->processEntity(new PipelineTestEntity(1, 'node', ['title' => 'x']));
        $this->assertTrue(true);
    }
}

final readonly class PipelineTestEntity implements EntityInterface
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private int|string|null $id,
        private string $entityTypeId,
        private array $values,
    ) {}

    public function id(): int|string|null { return $this->id; }
    public function uuid(): string { return ''; }
    public function label(): string { return (string) ($this->values['title'] ?? ''); }
    public function getEntityTypeId(): string { return $this->entityTypeId; }
    public function bundle(): string { return 'default'; }
    public function isNew(): bool { return false; }
    public function toArray(): array { return $this->values; }
    public function language(): string { return 'en'; }
}

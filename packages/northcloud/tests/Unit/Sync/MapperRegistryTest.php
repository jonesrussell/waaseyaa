<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Tests\Unit\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;
use Waaseyaa\NorthCloud\Sync\NcHitToEntityMapperInterface;

/**
 * @covers \Waaseyaa\NorthCloud\Sync\MapperRegistry
 */
#[CoversClass(MapperRegistry::class)]
final class MapperRegistryTest extends TestCase
{
    #[Test]
    public function emptyRegistryReturnsNoMappers(): void
    {
        $registry = new MapperRegistry();

        $this->assertSame(0, $registry->count());
        $this->assertSame([], $registry->all());
        $this->assertSame([], $registry->mappersFor(['title' => 'anything']));
    }

    #[Test]
    public function mappersForReturnsOnlySupportingMappers(): void
    {
        $supporting = $this->mapperSupporting(true, 'a');
        $notSupporting = $this->mapperSupporting(false, 'b');
        $alsoSupporting = $this->mapperSupporting(true, 'c');

        $registry = new MapperRegistry();
        $registry->register($supporting);
        $registry->register($notSupporting);
        $registry->register($alsoSupporting);

        $result = $registry->mappersFor(['title' => 'anything']);

        $this->assertSame(3, $registry->count());
        $this->assertCount(2, $result);
        $this->assertSame('a', $result[0]->entityType());
        $this->assertSame('c', $result[1]->entityType());
    }

    private function mapperSupporting(bool $supports, string $entityType): NcHitToEntityMapperInterface
    {
        return new class ($supports, $entityType) implements NcHitToEntityMapperInterface {
            public function __construct(private bool $s, private string $t) {}
            public function entityType(): string
            {
                return $this->t;
            }
            public function supports(array $hit): bool
            {
                return $this->s;
            }
            public function map(array $hit): array
            {
                return [];
            }
            public function dedupField(): string
            {
                return 'source_url';
            }
        };
    }
}

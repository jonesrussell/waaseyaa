<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Attribute\EntityMetadataReader;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\BenchmarkFixture;

require_once __DIR__ . '/../Fixtures/AttributeFirstEntities/FactoryTestFixtures.php';

/**
 * Performance budget for {@see EntityType::fromClass()}.
 *
 * Source: NFR-001 (first call < 5ms) and NFR-002 (cached call < 0.1ms)
 * from kitty-specs/attribute-first-entity-definition-01KQ6DXE/spec.md.
 *
 * The fixture entity declares 13 #[Field] properties to exercise a
 * realistic reflection workload.
 *
 * Marked with the `benchmark` group so noisy CI runners can opt out via
 * `--exclude-group benchmark`.
 */
#[Group('benchmark')]
final class EntityTypeFromClassBenchmarkTest extends TestCase
{
    protected function setUp(): void
    {
        EntityType::clearFromClassCache();
        EntityMetadataReader::clearCache();
    }

    public function testFirstCallUnderFiveMilliseconds(): void
    {
        $start = \hrtime(true);
        EntityType::fromClass(BenchmarkFixture::class);
        $elapsedMs = (\hrtime(true) - $start) / 1_000_000;

        self::assertLessThan(
            5.0,
            $elapsedMs,
            \sprintf('First call took %.4f ms; NFR-001 budget is 5 ms.', $elapsedMs),
        );
    }

    public function testCachedCallUnderHundredMicroseconds(): void
    {
        // Warm both the EntityMetadataReader cache and EntityType::fromClass cache.
        EntityType::fromClass(BenchmarkFixture::class);

        $iterations = 1000;
        $start = \hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            EntityType::fromClass(BenchmarkFixture::class);
        }
        $perCallMs = ((\hrtime(true) - $start) / 1_000_000) / $iterations;

        self::assertLessThan(
            0.1,
            $perCallMs,
            \sprintf('Cached call avg %.6f ms; NFR-002 budget is 0.1 ms.', $perCallMs),
        );
    }
}

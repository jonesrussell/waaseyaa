<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit\Discovery;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\HasListingsInterface;
use Waaseyaa\Listing\ListingDefinition;
use Waaseyaa\Listing\ListingDiscoverer;

#[CoversClass(ListingDiscoverer::class)]
final class ListingDiscovererTest extends TestCase
{
    #[Test]
    public function returnsEmptyListWhenNoProviders(): void
    {
        $discoverer = new ListingDiscoverer([]);

        self::assertSame([], $discoverer->discover());
    }

    #[Test]
    public function skipsProvidersThatDoNotImplementHasListingsInterface(): void
    {
        $nonProvider = new \stdClass();
        $listingProvider = self::providerYielding([
            new ListingDefinition(id: 'foo', entityType: 'node'),
        ]);

        $discoverer = new ListingDiscoverer([$nonProvider, $listingProvider]);

        $result = $discoverer->discover();

        self::assertCount(1, $result);
        self::assertSame('foo', $result[0]->id);
    }

    #[Test]
    public function flattensListingsFromMultipleProviders(): void
    {
        $providerA = self::providerYielding([
            new ListingDefinition(id: 'a1', entityType: 'node'),
            new ListingDefinition(id: 'a2', entityType: 'node'),
        ]);
        $providerB = self::providerYielding([
            new ListingDefinition(id: 'b1', entityType: 'taxonomy_term'),
        ]);

        $discoverer = new ListingDiscoverer([$providerA, $providerB]);

        $result = $discoverer->discover();

        self::assertCount(3, $result);
        self::assertSame(['a1', 'a2', 'b1'], array_map(static fn(ListingDefinition $d) => $d->id, $result));
    }

    #[Test]
    public function preservesProviderOrderForDeterministicDiscovery(): void
    {
        $first = self::providerYielding([new ListingDefinition(id: 'first', entityType: 'node')]);
        $second = self::providerYielding([new ListingDefinition(id: 'second', entityType: 'node')]);

        $resultA = (new ListingDiscoverer([$first, $second]))->discover();
        $resultB = (new ListingDiscoverer([$second, $first]))->discover();

        self::assertSame(['first', 'second'], array_map(static fn(ListingDefinition $d) => $d->id, $resultA));
        self::assertSame(['second', 'first'], array_map(static fn(ListingDefinition $d) => $d->id, $resultB));
    }

    #[Test]
    public function throwsOnDuplicateListingIdAcrossProviders(): void
    {
        $providerA = self::providerYielding([new ListingDefinition(id: 'dup', entityType: 'node')]);
        $providerB = self::providerYielding([new ListingDefinition(id: 'dup', entityType: 'node')]);

        $discoverer = new ListingDiscoverer([$providerA, $providerB]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Duplicate listing id "dup"/');
        $this->expectExceptionMessageMatches('/' . preg_quote($providerA::class, '/') . '/');
        $this->expectExceptionMessageMatches('/' . preg_quote($providerB::class, '/') . '/');

        $discoverer->discover();
    }

    #[Test]
    public function throwsOnDuplicateListingIdWithinSingleProvider(): void
    {
        $provider = self::providerYielding([
            new ListingDefinition(id: 'same', entityType: 'node'),
            new ListingDefinition(id: 'same', entityType: 'taxonomy_term'),
        ]);

        $discoverer = new ListingDiscoverer([$provider]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Duplicate listing id "same"/');

        $discoverer->discover();
    }

    #[Test]
    public function acceptsIterableProvidersFromGenerator(): void
    {
        $generator = (static function (): \Generator {
            yield self::providerYielding([new ListingDefinition(id: 'g1', entityType: 'node')]);
            yield self::providerYielding([new ListingDefinition(id: 'g2', entityType: 'node')]);
        })();

        $discoverer = new ListingDiscoverer($generator);

        $result = $discoverer->discover();

        self::assertSame(['g1', 'g2'], array_map(static fn(ListingDefinition $d) => $d->id, $result));
    }

    /**
     * @param list<ListingDefinition> $listings
     */
    private static function providerYielding(array $listings): HasListingsInterface
    {
        return new class ($listings) implements HasListingsInterface {
            /**
             * @param list<ListingDefinition> $listings
             */
            public function __construct(private readonly array $listings) {}

            /** @return list<ListingDefinition> */
            public function listings(): array
            {
                return $this->listings;
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Hydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Exception\InvalidConfigurationException;
use Waaseyaa\Entity\Hydration\FallbackChainResolver;
use Waaseyaa\Entity\TranslatableInterface;

#[CoversClass(FallbackChainResolver::class)]
#[CoversClass(InvalidConfigurationException::class)]
final class FallbackChainResolverTest extends TestCase
{
    #[Test]
    public function yieldsDeduplicatedLangcodesPreservingOrder(): void
    {
        $resolver = new FallbackChainResolver(
            static fn (string $requested, EntityInterface $entity): array => ['fr', 'en', 'fr', 'de', 'en'],
        );

        $entity = $this->stubEntity();

        $result = \iterator_to_array($resolver->resolve('fr', $entity), false);

        self::assertSame(['fr', 'en', 'de'], $result);
    }

    #[Test]
    public function throwsWhenChainExceedsDefaultMaxLength(): void
    {
        $resolver = new FallbackChainResolver(
            static fn (string $requested, EntityInterface $entity): array => [
                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i',
            ],
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Fallback chain length 9 exceeds maximum 8');

        \iterator_to_array($resolver->resolve('a', $this->stubEntity()));
    }

    #[Test]
    public function throwsWhenChainExceedsCustomMaxLength(): void
    {
        $resolver = new FallbackChainResolver(
            chainFn: static fn (string $requested, EntityInterface $entity): array => ['fr', 'en', 'de'],
            maxChainLength: 2,
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Fallback chain length 3 exceeds maximum 2');

        \iterator_to_array($resolver->resolve('fr', $this->stubEntity()));
    }

    #[Test]
    public function resolveYieldsLazilyAndCanBeShortCircuited(): void
    {
        $callCount = 0;
        $resolver = new FallbackChainResolver(
            static function (string $requested, EntityInterface $entity) use (&$callCount): array {
                ++$callCount;

                return ['fr', 'en', 'de'];
            },
        );

        $first = null;
        foreach ($resolver->resolve('fr', $this->stubEntity()) as $lc) {
            $first = $lc;
            break;
        }

        self::assertSame('fr', $first);
        self::assertSame(1, $callCount, 'chainFn should be invoked exactly once per resolve()');
    }

    #[Test]
    public function defaultChainFactoryFiltersEmptyAndDeduplicates(): void
    {
        $resolver = FallbackChainResolver::withDefaultChain('en');

        $entity = $this->stubTranslatableEntity('fr');
        $result = \iterator_to_array($resolver->resolve('fr', $entity), false);

        // requested=fr, entity-default=fr, site-default=en, terminal=en → de-duped to ['fr', 'en'].
        self::assertSame(['fr', 'en'], $result);
    }

    #[Test]
    public function defaultChainFactoryWithNonTranslatableEntitySkipsEntityDefault(): void
    {
        $resolver = FallbackChainResolver::withDefaultChain('de');

        $entity = $this->stubEntity();
        $result = \iterator_to_array($resolver->resolve('fr', $entity), false);

        // requested=fr, no entity-default, site-default=de, terminal=en.
        self::assertSame(['fr', 'de', 'en'], $result);
    }

    #[Test]
    public function defaultChainFactoryFiltersEmptyRequestedString(): void
    {
        $resolver = FallbackChainResolver::withDefaultChain('en');

        $result = \iterator_to_array($resolver->resolve('', $this->stubEntity()), false);

        // Empty requested is filtered; site-default + terminal collapse to ['en'].
        self::assertSame(['en'], $result);
    }

    private function stubEntity(): EntityInterface
    {
        return $this->createStub(EntityInterface::class);
    }

    private function stubTranslatableEntity(string $defaultLc): EntityInterface
    {
        $stub = $this->createStub(StubTranslatable::class);
        $stub->method('defaultLangcode')->willReturn($defaultLc);
        $stub->method('activeLangcode')->willReturn($defaultLc);
        $stub->method('language')->willReturn($defaultLc);

        return $stub;
    }
}

/**
 * Helper interface that composes EntityInterface and TranslatableInterface so the
 * test can `createStub()` a single object satisfying both — PHPUnit's createStub()
 * cannot mock intersection types directly.
 */
interface StubTranslatable extends EntityInterface, TranslatableInterface
{
}

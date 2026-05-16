<?php

declare(strict_types=1);

namespace Waaseyaa\Cache\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\CacheBackendInterface;
use Waaseyaa\Cache\Exception\InvalidCacheTagException;
use Waaseyaa\Cache\TaggedCacheInterface;

/**
 * Contract test for {@see TaggedCacheInterface}.
 *
 * Verifies the SHAPE of the interface (it is the v0.x stable surface of the
 * tagged-cache contract). Behaviour is tested separately per-implementation
 * (see {@see MemoryBackendTaggedTest}).
 */
#[CoversNothing]
final class TaggedCacheInterfaceContractTest extends TestCase
{
    #[Test]
    public function extendsCacheBackendInterface(): void
    {
        $reflection = new \ReflectionClass(TaggedCacheInterface::class);

        // The contract document originally said "extends CacheInterface" but
        // this repository's canonical cache contract is CacheBackendInterface.
        // Either name resolves to the same thing here.
        self::assertTrue(
            $reflection->implementsInterface(CacheBackendInterface::class),
            'TaggedCacheInterface must extend CacheBackendInterface',
        );
    }

    #[Test]
    public function declaresSetWithTags(): void
    {
        $method = new \ReflectionMethod(TaggedCacheInterface::class, 'setWithTags');

        self::assertSame('void', (string) $method->getReturnType());
        $params = $method->getParameters();
        self::assertCount(4, $params);
        self::assertSame('key', $params[0]->getName());
        self::assertSame('value', $params[1]->getName());
        self::assertSame('tags', $params[2]->getName());
        self::assertSame('ttl', $params[3]->getName());

        // `$ttl` is the only nullable, optional parameter.
        self::assertTrue($params[3]->allowsNull());
        self::assertTrue($params[3]->isOptional());
        self::assertNull($params[3]->getDefaultValue());

        // setWithTags MUST be documented as throwing InvalidCacheTagException.
        $docComment = $method->getDocComment();
        self::assertIsString($docComment);
        self::assertStringContainsString('InvalidCacheTagException', $docComment);
    }

    #[Test]
    public function declaresInvalidateByTag(): void
    {
        $method = new \ReflectionMethod(TaggedCacheInterface::class, 'invalidateByTag');

        self::assertSame('int', (string) $method->getReturnType());
        $params = $method->getParameters();
        self::assertCount(1, $params);
        self::assertSame('tag', $params[0]->getName());
    }

    #[Test]
    public function declaresGetTagsFor(): void
    {
        $method = new \ReflectionMethod(TaggedCacheInterface::class, 'getTagsFor');

        self::assertSame('array', (string) $method->getReturnType());
        $params = $method->getParameters();
        self::assertCount(1, $params);
        self::assertSame('key', $params[0]->getName());
    }

    #[Test]
    public function exposesCanonicalTagRegex(): void
    {
        // FR-034: the canonical tag regex MUST be exposed as part of the
        // interface so implementations and tests share a single source.
        self::assertTrue(\defined(TaggedCacheInterface::class . '::TAG_REGEX'));
        self::assertSame('/^[a-z][a-z0-9_:.-]*$/', TaggedCacheInterface::TAG_REGEX);
    }

    #[Test]
    public function invalidCacheTagExceptionExtendsInvalidArgumentException(): void
    {
        // Contract guarantee: the exception is catchable as
        // \InvalidArgumentException for callers that don't depend on the
        // concrete type (FR-034 mentions InvalidArgumentException by name).
        self::assertTrue(
            is_subclass_of(InvalidCacheTagException::class, \InvalidArgumentException::class),
        );
    }
}

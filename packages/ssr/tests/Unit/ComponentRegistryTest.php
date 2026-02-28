<?php

declare(strict_types=1);

namespace Aurora\SSR\Tests\Unit;

use Aurora\SSR\Attribute\Component;
use Aurora\SSR\ComponentMetadata;
use Aurora\SSR\ComponentRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComponentRegistry::class)]
final class ComponentRegistryTest extends TestCase
{
    #[Test]
    public function registerAndGet(): void
    {
        $registry = new ComponentRegistry();
        $metadata = new ComponentMetadata(
            name: 'article',
            template: 'components/article.html.twig',
            className: 'App\\Component\\ArticleComponent',
        );

        $registry->register($metadata);

        $this->assertSame($metadata, $registry->get('article'));
    }

    #[Test]
    public function getReturnsNullForUnknown(): void
    {
        $registry = new ComponentRegistry();

        $this->assertNull($registry->get('nonexistent'));
    }

    #[Test]
    public function hasReturnsTrueForRegistered(): void
    {
        $registry = new ComponentRegistry();
        $registry->register(new ComponentMetadata(
            name: 'article',
            template: 'components/article.html.twig',
            className: 'App\\Component\\ArticleComponent',
        ));

        $this->assertTrue($registry->has('article'));
    }

    #[Test]
    public function hasReturnsFalseForUnknown(): void
    {
        $registry = new ComponentRegistry();

        $this->assertFalse($registry->has('nonexistent'));
    }

    #[Test]
    public function allReturnsAllRegistered(): void
    {
        $registry = new ComponentRegistry();
        $meta1 = new ComponentMetadata('article', 'article.twig', 'A');
        $meta2 = new ComponentMetadata('page', 'page.twig', 'B');

        $registry->register($meta1);
        $registry->register($meta2);

        $all = $registry->all();

        $this->assertCount(2, $all);
        $this->assertContains($meta1, $all);
        $this->assertContains($meta2, $all);
    }

    #[Test]
    public function allReturnsEmptyArrayWhenEmpty(): void
    {
        $registry = new ComponentRegistry();

        $this->assertSame([], $registry->all());
    }

    #[Test]
    public function registerClassWithAnnotatedClass(): void
    {
        $registry = new ComponentRegistry();
        $registry->registerClass(RegistryTestArticle::class);

        $this->assertTrue($registry->has('registry-test-article'));

        $metadata = $registry->get('registry-test-article');
        $this->assertNotNull($metadata);
        $this->assertSame('registry-test-article', $metadata->name);
        $this->assertSame('components/registry-test-article.html.twig', $metadata->template);
        $this->assertSame(RegistryTestArticle::class, $metadata->className);
    }

    #[Test]
    public function registerClassThrowsForUnannotatedClass(): void
    {
        $registry = new ComponentRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not have a #[Component] attribute');

        $registry->registerClass(RegistryTestPlainClass::class);
    }

    #[Test]
    public function registerThrowsOnDuplicateName(): void
    {
        $registry = new ComponentRegistry();
        $meta1 = new ComponentMetadata('article', 'old.twig', 'OldClass');
        $meta2 = new ComponentMetadata('article', 'new.twig', 'NewClass');

        $registry->register($meta1);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Component "article" is already registered');

        $registry->register($meta2);
    }
}

#[Component(name: 'registry-test-article', template: 'components/registry-test-article.html.twig')]
final class RegistryTestArticle {}

final class RegistryTestPlainClass {}

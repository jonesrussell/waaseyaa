<?php

declare(strict_types=1);

namespace Aurora\SSR\Tests\Unit;

use Aurora\SSR\ComponentMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComponentMetadata::class)]
final class ComponentMetadataTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $metadata = new ComponentMetadata(
            name: 'article',
            template: 'components/article.html.twig',
            className: 'App\\Component\\ArticleComponent',
        );

        $this->assertSame('article', $metadata->name);
        $this->assertSame('components/article.html.twig', $metadata->template);
        $this->assertSame('App\\Component\\ArticleComponent', $metadata->className);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(ComponentMetadata::class);

        $this->assertTrue($reflection->isReadOnly());
    }
}

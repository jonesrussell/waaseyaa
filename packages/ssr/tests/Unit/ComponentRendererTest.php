<?php

declare(strict_types=1);

namespace Aurora\SSR\Tests\Unit;

use Aurora\SSR\Attribute\Component;
use Aurora\SSR\ComponentMetadata;
use Aurora\SSR\ComponentRegistry;
use Aurora\SSR\ComponentRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(ComponentRenderer::class)]
final class ComponentRendererTest extends TestCase
{
    private Environment $twig;
    private ComponentRegistry $registry;
    private ComponentRenderer $renderer;

    protected function setUp(): void
    {
        $loader = new ArrayLoader([
            'components/article.html.twig' => '<h1>{{ title }}</h1><p>{{ body }}</p>',
            'components/greeting.html.twig' => 'Hello, {{ name }}!',
            'components/empty.html.twig' => '<div></div>',
        ]);
        $this->twig = new Environment($loader);

        $this->registry = new ComponentRegistry();
        $this->registry->register(new ComponentMetadata(
            name: 'article',
            template: 'components/article.html.twig',
            className: RendererTestArticle::class,
        ));
        $this->registry->register(new ComponentMetadata(
            name: 'greeting',
            template: 'components/greeting.html.twig',
            className: 'App\\Greeting',
        ));
        $this->registry->register(new ComponentMetadata(
            name: 'empty',
            template: 'components/empty.html.twig',
            className: 'App\\Empty',
        ));

        $this->renderer = new ComponentRenderer($this->twig, $this->registry);
    }

    #[Test]
    public function renderProducesCorrectHtml(): void
    {
        $html = $this->renderer->render('article', [
            'title' => 'My Article',
            'body' => 'Article content here.',
        ]);

        $this->assertSame('<h1>My Article</h1><p>Article content here.</p>', $html);
    }

    #[Test]
    public function renderWithEmptyProps(): void
    {
        $html = $this->renderer->render('empty');

        $this->assertSame('<div></div>', $html);
    }

    #[Test]
    public function renderThrowsForUnknownComponent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Component "nonexistent" not found.');

        $this->renderer->render('nonexistent');
    }

    #[Test]
    public function renderObjectExtractsPublicProperties(): void
    {
        $component = new RendererTestArticle(
            title: 'Object Title',
            body: 'Object body text.',
        );

        $html = $this->renderer->renderObject($component);

        $this->assertSame('<h1>Object Title</h1><p>Object body text.</p>', $html);
    }

    #[Test]
    public function renderObjectThrowsForUnannotatedObject(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not have a #[Component] attribute');

        $this->renderer->renderObject(new RendererTestPlainObject());
    }
}

#[Component(name: 'renderer-test-article', template: 'components/article.html.twig')]
final class RendererTestArticle
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
    ) {}
}

final class RendererTestPlainObject {}

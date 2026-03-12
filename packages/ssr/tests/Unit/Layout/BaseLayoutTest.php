<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Unit\Layout;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Waaseyaa\SSR\Twig\WaaseyaaExtension;

#[CoversNothing]
final class BaseLayoutTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $templateDir = dirname(__DIR__, 3) . '/templates';
        if (!is_dir($templateDir)) {
            $this->markTestSkipped('SSR templates directory not found');
        }

        $this->twig = new Environment(new FilesystemLoader($templateDir));
        $this->twig->addExtension(new WaaseyaaExtension(assetBasePath: '/build'));
    }

    // ---------------------------------------------------------------
    // Base layout structure
    // ---------------------------------------------------------------

    #[Test]
    public function base_layout_renders_valid_html_document(): void
    {
        $html = $this->twig->render('layouts/base.html.twig');

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString('</head>', $html);
        $this->assertStringContainsString('<body>', $html);
        $this->assertStringContainsString('</body>', $html);
    }

    #[Test]
    public function base_layout_contains_header_and_footer_placeholders(): void
    {
        $html = $this->twig->render('layouts/base.html.twig');

        $this->assertStringContainsString('<header>', $html);
        $this->assertStringContainsString('</header>', $html);
        $this->assertStringContainsString('<footer>', $html);
        $this->assertStringContainsString('</footer>', $html);
    }

    #[Test]
    public function base_layout_includes_viewport_meta(): void
    {
        $html = $this->twig->render('layouts/base.html.twig');

        $this->assertStringContainsString('name="viewport"', $html);
        $this->assertStringContainsString('charset="utf-8"', $html);
    }

    #[Test]
    public function base_layout_accepts_lang_attribute(): void
    {
        $html = $this->twig->render('layouts/base.html.twig', ['lang' => 'fr']);

        $this->assertStringContainsString('lang="fr"', $html);
    }

    #[Test]
    public function base_layout_defaults_to_english(): void
    {
        $html = $this->twig->render('layouts/base.html.twig');

        $this->assertStringContainsString('lang="en"', $html);
    }

    // ---------------------------------------------------------------
    // Block overrides
    // ---------------------------------------------------------------

    #[Test]
    public function child_template_can_override_title_block(): void
    {
        $this->twig->getLoader()->addPath($this->fixtureDir());
        $html = $this->twig->render('child_title.html.twig');

        $this->assertStringContainsString('<title>Custom Title</title>', $html);
    }

    #[Test]
    public function child_template_can_override_body_block(): void
    {
        $this->twig->getLoader()->addPath($this->fixtureDir());
        $html = $this->twig->render('child_body.html.twig');

        $this->assertStringContainsString('<h1>Hello World</h1>', $html);
        $this->assertStringContainsString('<main>', $html);
    }

    #[Test]
    public function child_template_can_add_styles_block(): void
    {
        $this->twig->getLoader()->addPath($this->fixtureDir());
        $html = $this->twig->render('child_styles.html.twig');

        $this->assertStringContainsString('<link rel="stylesheet" href="/build/css/custom.css">', $html);
    }

    #[Test]
    public function child_template_can_add_scripts_block(): void
    {
        $this->twig->getLoader()->addPath($this->fixtureDir());
        $html = $this->twig->render('child_scripts.html.twig');

        $this->assertStringContainsString('<script src="/build/js/app.js"></script>', $html);
    }

    #[Test]
    public function child_template_can_add_meta_block(): void
    {
        $this->twig->getLoader()->addPath($this->fixtureDir());
        $html = $this->twig->render('child_meta.html.twig');

        $this->assertStringContainsString('<meta name="description" content="A test page">', $html);
    }

    // ---------------------------------------------------------------
    // Existing templates extend base
    // ---------------------------------------------------------------

    #[Test]
    public function page_template_extends_base_layout(): void
    {
        $html = $this->twig->render('page.html.twig', [
            'title' => 'Test Page',
            'path' => '/test',
        ]);

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('<title>Test Page</title>', $html);
        $this->assertStringContainsString('<h1>Test Page</h1>', $html);
        $this->assertStringContainsString('<header>', $html);
        $this->assertStringContainsString('<footer>', $html);
    }

    #[Test]
    public function error_404_template_extends_base_layout(): void
    {
        $html = $this->twig->render('404.html.twig', ['path' => '/missing']);

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('<title>404 Not Found</title>', $html);
        $this->assertStringContainsString('Not Found', $html);
        $this->assertStringContainsString('<header>', $html);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function fixtureDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/tests/fixtures/layouts';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Waaseyaa\SSR\Twig\WaaseyaaExtension;

#[CoversNothing]
final class HomepageTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $templateDir = dirname(__DIR__, 2) . '/templates';
        if (!is_dir($templateDir)) {
            $this->markTestSkipped('SSR templates directory not found');
        }

        $this->twig = new Environment(new FilesystemLoader($templateDir));
        $this->twig->addExtension(new WaaseyaaExtension(assetBasePath: '/build'));
    }

    #[Test]
    public function homepage_admin_link_does_not_point_to_backend_admin_route(): void
    {
        $html = $this->twig->render('home.html.twig');

        $this->assertStringNotContainsString(
            'href="/admin"',
            $html,
            'The /admin route does not exist on the PHP backend. The admin SPA runs on a separate Nuxt dev server.',
        );
    }

    #[Test]
    public function homepage_admin_link_points_to_nuxt_dev_server(): void
    {
        $html = $this->twig->render('home.html.twig');

        $this->assertStringContainsString(
            'localhost:3000',
            $html,
            'The admin SPA runs on the Nuxt dev server at localhost:3000.',
        );
    }
}

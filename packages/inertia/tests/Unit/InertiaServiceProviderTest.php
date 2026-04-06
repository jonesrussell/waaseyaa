<?php

declare(strict_types=1);

namespace Waaseyaa\Inertia\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaServiceProvider;
use Waaseyaa\Inertia\RootTemplateRenderer;

#[CoversClass(InertiaServiceProvider::class)]
final class InertiaServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        Inertia::reset();
    }

    protected function tearDown(): void
    {
        Inertia::reset();
    }

    #[Test]
    public function register_configures_renderer_with_asset_manager(): void
    {
        $tmpDir = sys_get_temp_dir() . '/waaseyaa-sp-' . uniqid();
        mkdir($tmpDir . '/public/build/.vite', 0777, true);
        file_put_contents($tmpDir . '/public/build/.vite/manifest.json', json_encode([
            'resources/js/app.ts' => [
                'file' => 'assets/app-test.js',
                'css' => ['assets/app-test.css'],
                'isEntry' => true,
            ],
        ]));

        $provider = new InertiaServiceProvider();
        $provider->registerWithRoot($tmpDir);

        $renderer = Inertia::getRenderer();
        $html = $renderer->render([
            'component' => 'Test',
            'props' => ['errors' => []],
            'url' => '/',
            'version' => '',
        ]);

        self::assertStringContainsString('app-test.js', $html);
        self::assertStringContainsString('app-test.css', $html);

        // Cleanup
        unlink($tmpDir . '/public/build/.vite/manifest.json');
        rmdir($tmpDir . '/public/build/.vite');
        rmdir($tmpDir . '/public/build');
        rmdir($tmpDir . '/public');
        rmdir($tmpDir);
    }
}

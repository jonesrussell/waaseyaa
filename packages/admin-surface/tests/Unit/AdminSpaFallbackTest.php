<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AdminSurface\AdminSpaFallback;

#[CoversClass(AdminSpaFallback::class)]
final class AdminSpaFallbackTest extends TestCase
{
    #[Test]
    public function response_contains_surface_paths_and_spec_link(): void
    {
        $response = AdminSpaFallback::htmlResponse('TestApp');

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getContent();
        $this->assertStringContainsString('/admin/_surface/session', $body);
        $this->assertStringContainsString('/admin/_surface/catalog', $body);
        $this->assertStringNotContainsString('/admin/surface/', $body);
        $this->assertStringContainsString(AdminSpaFallback::SPEC_URL, $body);
        $this->assertStringContainsString('TestApp', $body);
    }
}

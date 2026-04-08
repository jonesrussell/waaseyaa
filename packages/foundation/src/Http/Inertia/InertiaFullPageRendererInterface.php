<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http\Inertia;

/**
 * Renders a full HTML document embedding an Inertia page object (initial visit / non-XHR).
 */
interface InertiaFullPageRendererInterface
{
    /** @param array<string, mixed> $pageObject */
    public function render(array $pageObject): string;
}

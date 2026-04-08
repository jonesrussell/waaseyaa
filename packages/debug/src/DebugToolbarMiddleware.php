<?php

declare(strict_types=1);

namespace Waaseyaa\Debug;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Attribute\AsMiddleware;
use Waaseyaa\Foundation\Middleware\HttpHandlerInterface;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;

/**
 * Injects a minimal debug strip into HTML responses (APP_DEBUG only — caller should not register otherwise).
 */
#[AsMiddleware(pipeline: 'http', priority: 95)]
final class DebugToolbarMiddleware implements HttpMiddlewareInterface
{
    public function __construct(
        private readonly float $startTime = 0.0,
    ) {}

    public function process(Request $request, HttpHandlerInterface $next): Response
    {
        $response = $next->handle($request);
        $contentType = $response->headers->get('Content-Type', '');
        if (!is_string($contentType) || !str_contains($contentType, 'text/html')) {
            return $response;
        }

        $base = $this->startTime > 0.0 ? $this->startTime : (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $elapsedMs = (int) round((microtime(true) - $base) * 1000);
        $peakMb = round(memory_get_peak_usage(true) / 1_048_576, 1);

        $content = $response->getContent();
        if (!is_string($content) || $content === '' || !str_contains($content, '</body>')) {
            return $response;
        }

        $bar = <<<HTML
<div id="waaseyaa-debug-bar" style="position:fixed;left:0;right:0;bottom:0;z-index:2147483000;background:#111827;color:#e5e7eb;font:12px/1.4 system-ui;padding:6px 10px;display:flex;gap:16px;border-top:1px solid #374151;">
  <span>Waaseyaa</span><span>{$elapsedMs} ms</span><span>{$peakMb} MB</span>
</div>
HTML;

        $updated = str_replace('</body>', $bar . '</body>', $content, $count);
        if ($count < 1) {
            return $response;
        }

        $response->setContent($updated);

        return $response;
    }
}

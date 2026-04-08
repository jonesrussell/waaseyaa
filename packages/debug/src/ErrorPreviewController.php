<?php

declare(strict_types=1);

namespace Waaseyaa\Debug;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dev-only preview of generic error pages at /_error/{statusCode}.
 */
final class ErrorPreviewController
{
    public function __invoke(Request $request): Response
    {
        $raw = $request->attributes->get('statusCode', '500');
        $statusToken = is_scalar($raw) ? (string) $raw : '500';
        $code = (int) $statusToken;
        if ($code < 100 || $code > 599) {
            return new Response('Invalid status code', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $safe = htmlspecialchars((string) $code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Error {$safe}</title>
  <style>body{font-family:system-ui;margin:2rem;}</style>
</head>
<body>
  <h1>Error {$safe}</h1>
  <p>Preview of the production error shell (debug mode only).</p>
</body>
</html>
HTML;

        return new Response($body, $code, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

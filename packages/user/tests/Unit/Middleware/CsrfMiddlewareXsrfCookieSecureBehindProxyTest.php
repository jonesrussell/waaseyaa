<?php

declare(strict_types=1);

namespace Waaseyaa\User\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\User\Middleware\CsrfMiddleware;

/**
 * End-to-end verification of issue #1394: the XSRF-TOKEN cookie's
 * `Secure` flag tracks `$request->isSecure()`, which in turn must
 * honor `X-Forwarded-Proto` when a trusted reverse proxy has been
 * registered via `Request::setTrustedProxies()`.
 *
 * These tests do not boot a kernel — they assert the cookie-write
 * contract (`kitty-specs/inertia-file-upload-csrf-01KQZJQJ/contracts/csrf-token-cookie.md` §1)
 * by synthesizing the post-proxy Request shape directly and calling
 * the static helper that {@see \Waaseyaa\Foundation\Kernel\HttpKernel}
 * invokes after controller dispatch.
 *
 * `Request::setTrustedProxies()` mutates static (process-wide) state;
 * the test resets it in {@see tearDown()} to avoid cross-test pollution.
 */
#[CoversClass(CsrfMiddleware::class)]
final class CsrfMiddlewareXsrfCookieSecureBehindProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
    }

    #[Test]
    public function xsrf_cookie_has_secure_flag_when_trusted_proxy_forwards_https(): void
    {
        $this->primeSession();

        // Configure the loopback proxy as trusted, mirroring what
        // HttpKernel does at the start of serveHttpRequest().
        Request::setTrustedProxies(
            ['127.0.0.1'],
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PORT,
        );

        // The connecting peer is the trusted proxy; it forwards
        // X-Forwarded-Proto: https from the real client.
        $request = Request::create(
            uri: 'http://example.test/',
            method: 'GET',
            server: [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_HOST' => 'example.test',
            ],
        );

        $this->assertTrue(
            $request->isSecure(),
            'X-Forwarded-Proto: https from a trusted proxy must mark the request as secure.',
        );

        $response = new Response('<!doctype html><html></html>', 200, ['Content-Type' => 'text/html']);
        CsrfMiddleware::attachCookieIfHtml($request, $response);

        $cookie = $this->findXsrfCookie($response);
        $this->assertNotNull($cookie, 'XSRF-TOKEN cookie must be attached to HTML responses.');
        $this->assertTrue(
            $cookie->isSecure(),
            'Behind a TLS terminator with a trusted-proxy registration, the cookie MUST carry Secure.',
        );
    }

    #[Test]
    public function xsrf_cookie_omits_secure_flag_when_no_trusted_proxy_is_registered(): void
    {
        $this->primeSession();

        // No setTrustedProxies() call — Symfony will ignore
        // X-Forwarded-Proto entirely, which is the pre-fix behavior
        // and the documented safe default for setups without a TLS
        // terminator.
        $request = Request::create(
            uri: 'http://example.test/',
            method: 'GET',
            server: [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );

        $this->assertFalse(
            $request->isSecure(),
            'Without trusted proxies, X-Forwarded-Proto must be ignored.',
        );

        $response = new Response('<!doctype html><html></html>', 200, ['Content-Type' => 'text/html']);
        CsrfMiddleware::attachCookieIfHtml($request, $response);

        $cookie = $this->findXsrfCookie($response);
        $this->assertNotNull($cookie, 'XSRF-TOKEN cookie must still be attached.');
        $this->assertFalse(
            $cookie->isSecure(),
            'Without a trusted-proxy registration, Secure must remain unset (existing behavior).',
        );
    }

    private function primeSession(): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['_csrf_token'] = 'test-token-' . bin2hex(random_bytes(8));
    }

    private function findXsrfCookie(Response $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN') {
                return $cookie;
            }
        }

        return null;
    }
}

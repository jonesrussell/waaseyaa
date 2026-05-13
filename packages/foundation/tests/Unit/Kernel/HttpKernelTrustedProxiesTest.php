<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

/**
 * Covers the trusted-proxy wiring in {@see HttpKernel} (issue #1394).
 *
 * The kernel calls `Request::setTrustedProxies()` early in
 * `serveHttpRequest()` so that `$request->isSecure()` honors
 * `X-Forwarded-Proto` when the framework sits behind a TLS-terminating
 * reverse proxy (Caddy, nginx, load balancers).
 *
 * The wiring sources its list from:
 *   1. `$this->config['trusted_proxies']` — typed, wins when set.
 *   2. `getenv('TRUSTED_PROXIES')` — comma-separated CIDRs / IPs / the
 *      Symfony `REMOTE_ADDR` sentinel.
 *
 * Both `Request::setTrustedProxies()` and `Request::getTrustedProxies()`
 * mutate / read static (process-wide) state; the test resets it in
 * {@see tearDown()} to avoid cross-test pollution.
 */
#[CoversClass(HttpKernel::class)]
final class HttpKernelTrustedProxiesTest extends TestCase
{
    private ?string $previousEnv = null;

    protected function setUp(): void
    {
        // Capture and clear the env var so each test sees a clean slate.
        $current = getenv('TRUSTED_PROXIES');
        $this->previousEnv = ($current === false) ? null : $current;
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);

        // Wipe static request state too, so tests inherit a clean baseline.
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }

    protected function tearDown(): void
    {
        // Reset Symfony's static trusted-proxy state to avoid polluting
        // sibling tests that read $request->isSecure() / etc.
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

        // Restore the env var we may have stomped during the test.
        if ($this->previousEnv === null) {
            putenv('TRUSTED_PROXIES');
            unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        } else {
            putenv('TRUSTED_PROXIES=' . $this->previousEnv);
            $_ENV['TRUSTED_PROXIES'] = $this->previousEnv;
            $_SERVER['TRUSTED_PROXIES'] = $this->previousEnv;
        }
    }

    #[Test]
    public function no_config_and_no_env_does_not_register_any_trusted_proxies(): void
    {
        $kernel = new HttpKernel('/tmp/waaseyaa_tp_' . uniqid());
        $this->seedConfig($kernel, []);

        $this->invokeApply($kernel);

        $this->assertSame(
            [],
            Request::getTrustedProxies(),
            'No config and no env var must leave the trusted-proxy list empty.',
        );
    }

    #[Test]
    public function config_list_is_registered_with_the_standard_forwarded_header_set(): void
    {
        $kernel = new HttpKernel('/tmp/waaseyaa_tp_' . uniqid());
        $this->seedConfig($kernel, [
            'trusted_proxies' => ['10.0.0.0/8', '192.168.0.0/16'],
        ]);

        $this->invokeApply($kernel);

        $this->assertSame(
            ['10.0.0.0/8', '192.168.0.0/16'],
            Request::getTrustedProxies(),
        );
        $expectedHeaderSet = Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PORT;
        $this->assertSame(
            $expectedHeaderSet,
            Request::getTrustedHeaderSet(),
            'X-Forwarded-{For,Host,Proto,Port} must all be honored.',
        );
    }

    #[Test]
    public function env_var_is_used_when_config_is_empty(): void
    {
        putenv('TRUSTED_PROXIES=10.0.0.0/8');

        $kernel = new HttpKernel('/tmp/waaseyaa_tp_' . uniqid());
        $this->seedConfig($kernel, []);

        $this->invokeApply($kernel);

        $this->assertSame(['10.0.0.0/8'], Request::getTrustedProxies());
    }

    #[Test]
    public function env_var_remote_addr_sentinel_is_passed_through_to_symfony_verbatim(): void
    {
        // The Symfony `REMOTE_ADDR` sentinel must be passed to
        // `Request::setTrustedProxies()` literally — Symfony resolves
        // it at call time against `$_SERVER['REMOTE_ADDR']`. The
        // framework must NOT try to expand it itself. We assert the
        // pass-through by setting REMOTE_ADDR and confirming Symfony's
        // expansion of the sentinel matches.
        $previousRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '10.99.0.7';

        try {
            putenv('TRUSTED_PROXIES=REMOTE_ADDR');

            $kernel = new HttpKernel('/tmp/waaseyaa_tp_' . uniqid());
            $this->seedConfig($kernel, []);

            $this->invokeApply($kernel);

            $this->assertSame(
                ['10.99.0.7'],
                Request::getTrustedProxies(),
                'Symfony must resolve the REMOTE_ADDR sentinel against $_SERVER at registration time.',
            );
        } finally {
            if ($previousRemoteAddr === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $previousRemoteAddr;
            }
        }
    }

    #[Test]
    public function env_var_whitespace_and_empty_entries_are_trimmed(): void
    {
        putenv('TRUSTED_PROXIES=10.0.0.0/8 , 192.168.0.0/16 , ,  ');

        $kernel = new HttpKernel('/tmp/waaseyaa_tp_' . uniqid());
        $this->seedConfig($kernel, []);

        $this->invokeApply($kernel);

        $this->assertSame(
            ['10.0.0.0/8', '192.168.0.0/16'],
            Request::getTrustedProxies(),
        );
    }

    #[Test]
    public function config_wins_over_env_var_when_both_are_set(): void
    {
        putenv('TRUSTED_PROXIES=10.0.0.0/8');

        $kernel = new HttpKernel('/tmp/waaseyaa_tp_' . uniqid());
        $this->seedConfig($kernel, [
            'trusted_proxies' => ['172.16.0.0/12'],
        ]);

        $this->invokeApply($kernel);

        $this->assertSame(
            ['172.16.0.0/12'],
            Request::getTrustedProxies(),
            'Typed config must take precedence over the TRUSTED_PROXIES env var.',
        );
    }

    #[Test]
    public function non_string_config_entries_are_ignored_safely(): void
    {
        $kernel = new HttpKernel('/tmp/waaseyaa_tp_' . uniqid());
        $this->seedConfig($kernel, [
            // PHP array of mixed values — wiring should drop non-strings,
            // not crash. A misconfigured app.php should fail safe.
            'trusted_proxies' => ['10.0.0.0/8', 42, null, '', '  ', '192.168.0.0/16'],
        ]);

        $this->invokeApply($kernel);

        $this->assertSame(
            ['10.0.0.0/8', '192.168.0.0/16'],
            Request::getTrustedProxies(),
        );
    }

    /**
     * Set the kernel's protected `$config` property to the given array.
     *
     * @param array<string, mixed> $config
     */
    private function seedConfig(HttpKernel $kernel, array $config): void
    {
        $reflection = new \ReflectionProperty(AbstractKernel::class, 'config');
        $reflection->setValue($kernel, $config);
    }

    private function invokeApply(HttpKernel $kernel): void
    {
        $method = new \ReflectionMethod(HttpKernel::class, 'applyTrustedProxiesFromConfig');
        $method->invoke($kernel);
    }
}

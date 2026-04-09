<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\SSR\RenderController;

#[CoversClass(RenderController::class)]
final class RenderControllerDefaultSiteNameTest extends TestCase
{
    #[Test]
    public function empty_app_name_in_env_falls_through_to_server(): void
    {
        $this->preserveAppNameGlobals(function (): void {
            $_ENV['APP_NAME'] = '';
            $_SERVER['APP_NAME'] = 'FromServer';
            \putenv('APP_NAME');

            self::assertSame('FromServer', $this->invokeResolveDefaultSiteName());
        });
    }

    #[Test]
    public function empty_app_name_in_env_and_server_falls_through_to_getenv(): void
    {
        $this->preserveAppNameGlobals(function (): void {
            $_ENV['APP_NAME'] = '';
            unset($_SERVER['APP_NAME']);
            \putenv('APP_NAME=FromGetenv');

            self::assertSame('FromGetenv', $this->invokeResolveDefaultSiteName());

            \putenv('APP_NAME');
        });
    }

    #[Test]
    public function empty_strings_everywhere_yield_waaseyaa_default(): void
    {
        $this->preserveAppNameGlobals(function (): void {
            $_ENV['APP_NAME'] = '';
            $_SERVER['APP_NAME'] = '';
            \putenv('APP_NAME=');

            self::assertSame('Waaseyaa', $this->invokeResolveDefaultSiteName());
        });
    }

    private function invokeResolveDefaultSiteName(): string
    {
        $ref = new \ReflectionClass(RenderController::class);
        $method = $ref->getMethod('resolveDefaultSiteName');

        return $method->invoke(null);
    }

    /**
     * @param callable():void $callback
     */
    private function preserveAppNameGlobals(callable $callback): void
    {
        $hadEnv = \array_key_exists('APP_NAME', $_ENV);
        $prevEnv = $hadEnv ? $_ENV['APP_NAME'] : null;
        $hadServer = \array_key_exists('APP_NAME', $_SERVER);
        $prevServer = $hadServer ? $_SERVER['APP_NAME'] : null;
        $prevGetenv = \getenv('APP_NAME');

        try {
            $callback();
        } finally {
            if ($hadEnv) {
                $_ENV['APP_NAME'] = $prevEnv;
            } else {
                unset($_ENV['APP_NAME']);
            }
            if ($hadServer) {
                $_SERVER['APP_NAME'] = $prevServer;
            } else {
                unset($_SERVER['APP_NAME']);
            }
            if ($prevGetenv !== false && $prevGetenv !== '') {
                \putenv('APP_NAME=' . $prevGetenv);
            } else {
                \putenv('APP_NAME');
            }
        }
    }
}

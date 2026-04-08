<?php

declare(strict_types=1);

namespace Waaseyaa\Debug;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class DebugServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $router->addRoute(
            'debug.error_preview',
            RouteBuilder::create('/_error/{statusCode}')
                ->controller(new ErrorPreviewController())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }

    public function middleware(EntityTypeManager $entityTypeManager): array
    {
        if (!$this->isDebugEnabled()) {
            return [];
        }

        $start = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

        return [
            new DebugToolbarMiddleware($start),
        ];
    }

    private function isDebugEnabled(): bool
    {
        $env = getenv('APP_DEBUG');
        if (is_string($env) && $env !== '') {
            return filter_var($env, FILTER_VALIDATE_BOOLEAN);
        }

        return filter_var($this->config['debug'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}

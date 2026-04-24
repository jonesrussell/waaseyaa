<?php

declare(strict_types=1);

namespace Waaseyaa\Routing;

use Waaseyaa\Auth\Config\AuthConfig;
use Waaseyaa\Auth\Controller\ForgotPasswordController;
use Waaseyaa\Auth\Controller\LoginController;
use Waaseyaa\Auth\Controller\LogoutController;
use Waaseyaa\Auth\Controller\MeController;
use Waaseyaa\Auth\Controller\RegisterController;
use Waaseyaa\Auth\Controller\ResendVerificationController;
use Waaseyaa\Auth\Controller\ResetPasswordController;
use Waaseyaa\Auth\Controller\VerifyEmailController;
use Waaseyaa\Auth\RateLimiterInterface;
use Waaseyaa\Auth\Token\AuthTokenRepositoryInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Oidc\Authorize\AuthorizeController;
use Waaseyaa\Oidc\Token\TokenController;
use Waaseyaa\User\AuthMailer;

/**
 * Registers auth and OIDC HTTP routes. Layer 4: uses RouteBuilder / WaaseyaaRouter only here,
 * not in waaseyaa/auth or waaseyaa/oidc service providers.
 */
final class AuthOidcRouteServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $this->registerAuthRoutes($router, $entityTypeManager);
        $this->registerOidcRoutes($router);
    }

    private function registerAuthRoutes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager): void
    {
        $authConfig = $this->resolve(AuthConfig::class);
        $tokenRepo = $this->resolve(AuthTokenRepositoryInterface::class);
        $rateLimiter = $this->resolve(RateLimiterInterface::class);
        $authMailer = $this->resolve(AuthMailer::class);
        $etm = $entityTypeManager ?? $this->resolve(EntityTypeManager::class);

        $router->addRoute(
            'api.auth.register',
            RouteBuilder::create('/api/auth/register')
                ->controller(new RegisterController(
                    config: $authConfig,
                    entityTypeManager: $etm,
                    tokenRepo: $tokenRepo,
                    authMailer: $authMailer,
                    rateLimiter: $rateLimiter,
                ))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.auth.forgot_password',
            RouteBuilder::create('/api/auth/forgot-password')
                ->controller(new ForgotPasswordController(
                    config: $authConfig,
                    entityTypeManager: $etm,
                    tokenRepo: $tokenRepo,
                    authMailer: $authMailer,
                    rateLimiter: $rateLimiter,
                ))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.auth.reset_password',
            RouteBuilder::create('/api/auth/reset-password')
                ->controller(new ResetPasswordController(
                    entityTypeManager: $etm,
                    tokenRepo: $tokenRepo,
                ))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.auth.verify_email',
            RouteBuilder::create('/api/auth/verify-email')
                ->controller(new VerifyEmailController(
                    entityTypeManager: $etm,
                    tokenRepo: $tokenRepo,
                ))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.auth.resend_verification',
            RouteBuilder::create('/api/auth/resend-verification')
                ->controller(new ResendVerificationController(
                    config: $authConfig,
                    entityTypeManager: $etm,
                    tokenRepo: $tokenRepo,
                    authMailer: $authMailer,
                    rateLimiter: $rateLimiter,
                ))
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.auth.login',
            RouteBuilder::create('/api/auth/login')
                ->controller(new LoginController(
                    entityTypeManager: $etm,
                    rateLimiter: $rateLimiter,
                ))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.auth.logout',
            RouteBuilder::create('/api/auth/logout')
                ->controller(new LogoutController())
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.user.me',
            RouteBuilder::create('/api/user/me')
                ->controller(new MeController())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }

    private function registerOidcRoutes(WaaseyaaRouter $router): void
    {
        $authorizeController = null;
        try {
            $authorizeController = $this->resolve(AuthorizeController::class);
        } catch (\Throwable) {
        }

        $tokenController = null;
        try {
            $tokenController = $this->resolve(TokenController::class);
        } catch (\Throwable) {
        }

        (new OidcHttpRoutes(
            authorizeController: $authorizeController,
            tokenController: $tokenController,
        ))->registerRoutes($router);
    }
}

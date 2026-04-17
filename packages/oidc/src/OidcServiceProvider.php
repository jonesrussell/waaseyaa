<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Scaffold per ADR-006. Implementation lands in follow-up PRs (discovery,
 * JWKS, authorization code flow, token, userinfo, revocation, logout).
 */
final class OidcServiceProvider extends ServiceProvider
{
    public function register(): void {}
}

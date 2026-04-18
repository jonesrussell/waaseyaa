<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Authorize;

/**
 * Classified authorization request rejection.
 *
 * When `$redirectUri` is null, the caller must render a direct error page: the
 * client or redirect_uri is unverified and the spec forbids redirecting to an
 * unregistered URI. When `$redirectUri` is non-null, the caller must redirect
 * there with `error` (and `state` if present) per OAuth 2.0 §4.1.2.1.
 */
final class AuthorizationRequestException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $errorDescription,
        public readonly ?string $redirectUri = null,
        public readonly ?string $state = null,
    ) {
        parent::__construct($errorCode . ': ' . $errorDescription);
    }

    public function canRedirect(): bool
    {
        return $this->redirectUri !== null;
    }
}

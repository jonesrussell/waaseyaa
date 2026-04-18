<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Token;

/**
 * A validated /token request body (authorization_code grant).
 *
 * clientId is nullable because a confidential client may authenticate via
 * HTTP Basic instead of sending client_id in the form body (RFC 6749 §2.3.1).
 * The controller reconciles the two sources.
 */
final class TokenRequest
{
    public function __construct(
        public readonly string $code,
        public readonly string $redirectUri,
        public readonly string $codeVerifier,
        public readonly ?string $clientId,
    ) {}
}

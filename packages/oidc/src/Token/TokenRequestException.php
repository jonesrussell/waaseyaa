<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Token;

use RuntimeException;

/**
 * Thrown when a /token request fails validation.
 *
 * errorCode values come from RFC 6749 §5.2: invalid_request, invalid_client,
 * invalid_grant, unauthorized_client, unsupported_grant_type, invalid_scope.
 */
final class TokenRequestException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $errorDescription,
    ) {
        parent::__construct($errorDescription);
    }
}

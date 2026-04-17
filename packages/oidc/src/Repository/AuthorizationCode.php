<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Repository;

/**
 * Short-lived, single-use authorization code issued by the OIDC authorize endpoint
 * and exchanged at the token endpoint for an ID token + access token.
 *
 * Per OAuth 2.1, codes live for 60 seconds and may be consumed exactly once. Binding
 * fields (clientId, redirectUri, codeChallenge) are validated at exchange time — the
 * repository stores them verbatim; it does not enforce them.
 */
final readonly class AuthorizationCode
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $code,
        public string $clientId,
        public string $accountId,
        public string $redirectUri,
        public array $scopes,
        public string $codeChallenge,
        public string $codeChallengeMethod,
        public int $issuedAt,
        public int $expiresAt,
        public ?int $consumedAt = null,
    ) {}

    public function isExpired(int $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }
}

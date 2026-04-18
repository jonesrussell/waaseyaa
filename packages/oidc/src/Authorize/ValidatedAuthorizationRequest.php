<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Authorize;

use Waaseyaa\Oidc\Entity\OidcClient;

/**
 * Output of AuthorizationRequestValidator. Carries every field the authorize
 * controller needs to issue a code and redirect back to the relying party.
 */
final readonly class ValidatedAuthorizationRequest
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        public OidcClient $client,
        public string $redirectUri,
        public array $scopes,
        public string $codeChallenge,
        public string $codeChallengeMethod,
        public ?string $state = null,
        public ?string $nonce = null,
    ) {}
}

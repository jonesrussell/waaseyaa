<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @api
 */
final readonly class DiscoveryController
{
    public function __construct(private string $issuer) {}

    public function serve(): JsonResponse
    {
        return new JsonResponse([
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->issuer . '/authorize',
            'token_endpoint' => $this->issuer . '/token',
            'userinfo_endpoint' => $this->issuer . '/userinfo',
            'jwks_uri' => $this->issuer . '/.well-known/jwks.json',
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
        ]);
    }
}

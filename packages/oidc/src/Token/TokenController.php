<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Token;

use Closure;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Oidc\ClientRegistry\OidcClientLookup;
use Waaseyaa\Oidc\Repository\AuthorizationCodeRepositoryInterface;

/**
 * OAuth 2.1 / OIDC token endpoint (ADR-006 §7, OIDC Core §3.1.3).
 *
 * Exchanges a consumed authorization code for an RS256-signed ID token and an
 * opaque access token. Enforces PKCE S256 (ADR-006 §2.5), byte-exact
 * redirect_uri match, and client_id binding. Confidential clients authenticate
 * via HTTP Basic or client_secret_post (RFC 6749 §2.3.1); public clients rely
 * on PKCE alone.
 *
 * Non-goals (charter #1292): refresh tokens, /userinfo, consent screen,
 * at_hash, JWT access tokens.
 */
final readonly class TokenController
{
    private const ACCESS_TOKEN_EXPIRY = 600;

    /**
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        private OidcClientLookup $clientLookup,
        private TokenRequestValidator $validator,
        private PkceVerifier $pkceVerifier,
        private AuthorizationCodeRepositoryInterface $codeRepository,
        private IdTokenMinter $idTokenMinter,
        private string $issuer,
        private Closure $clock,
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->getMethod() !== 'POST') {
            return $this->error(405, 'invalid_request', 'POST required.');
        }

        try {
            $tokenRequest = $this->validator->validate($request->request->all());
        } catch (TokenRequestException $e) {
            return $this->error(400, $e->errorCode, $e->errorDescription);
        }

        [$credClientId, $credClientSecret] = $this->extractClientCredentials($request, $tokenRequest);
        if ($credClientId === null) {
            return $this->error(401, 'invalid_client', 'No client credentials provided.');
        }

        $client = $this->clientLookup->findByClientId($credClientId);
        if ($client === null) {
            return $this->error(401, 'invalid_client', 'Unknown client_id.');
        }

        if ($client->isConfidential()) {
            if ($credClientSecret === null) {
                return $this->error(401, 'invalid_client', 'Client authentication required.');
            }
            $hash = $client->getClientSecretHash();
            if ($hash === null || !password_verify($credClientSecret, $hash)) {
                return $this->error(401, 'invalid_client', 'Client authentication failed.');
            }
        }

        $stored = $this->codeRepository->consume($tokenRequest->code);
        if ($stored === null) {
            return $this->error(400, 'invalid_grant', 'Authorization code is invalid or expired.');
        }

        if ($stored->clientId !== $client->getClientId()) {
            return $this->error(400, 'invalid_grant', 'Authorization code was not issued to this client.');
        }

        if ($stored->redirectUri !== $tokenRequest->redirectUri) {
            return $this->error(400, 'invalid_grant', 'redirect_uri does not match the authorization request.');
        }

        if (!$this->pkceVerifier->verify(
            $tokenRequest->codeVerifier,
            $stored->codeChallenge,
            $stored->codeChallengeMethod,
        )) {
            return $this->error(400, 'invalid_grant', 'PKCE verifier does not match the stored code_challenge.');
        }

        $now = ($this->clock)();

        $idToken = $this->idTokenMinter->mint(
            issuer: $this->issuer,
            subject: $stored->accountId,
            audience: $client->getClientId(),
            nonce: $stored->nonce,
            now: $now,
        );

        $accessToken = $this->generateOpaqueToken();

        return $this->success([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_EXPIRY,
            'id_token' => $idToken,
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string} [client_id, client_secret]
     */
    private function extractClientCredentials(Request $request, TokenRequest $tokenRequest): array
    {
        $authHeader = $request->headers->get('Authorization');
        if (is_string($authHeader) && stripos($authHeader, 'Basic ') === 0) {
            $decoded = base64_decode(substr($authHeader, 6), true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                [$id, $secret] = explode(':', $decoded, 2);
                if ($id !== '') {
                    return [$id, $secret];
                }
            }
        }

        $bodySecret = $request->request->get('client_secret');
        if ($tokenRequest->clientId !== null) {
            return [
                $tokenRequest->clientId,
                is_string($bodySecret) && $bodySecret !== '' ? $bodySecret : null,
            ];
        }

        return [null, null];
    }

    private function generateOpaqueToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function success(array $payload): Response
    {
        $response = new JsonResponse($payload, 200);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function error(int $status, string $error, string $description): Response
    {
        $response = new JsonResponse([
            'error' => $error,
            'error_description' => $description,
        ], $status);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}

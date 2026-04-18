<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Token;

/**
 * Validates a POST /token form body for the authorization_code grant.
 *
 * Per RFC 6749 §4.1.3 and RFC 7636 §4.5. Client authentication (Basic vs
 * client_secret_post vs PKCE-only for public clients) is handled upstream by
 * the controller; this validator only checks structural correctness of the
 * form body and resolves the TokenRequest value object.
 */
final class TokenRequestValidator
{
    private const SUPPORTED_GRANT_TYPE = 'authorization_code';

    /**
     * @param array<string, mixed> $form
     */
    public function validate(array $form): TokenRequest
    {
        $grantType = $this->stringOrNull($form, 'grant_type');
        if ($grantType === null) {
            throw new TokenRequestException('invalid_request', 'Missing grant_type.');
        }

        if ($grantType !== self::SUPPORTED_GRANT_TYPE) {
            throw new TokenRequestException(
                'unsupported_grant_type',
                'Only grant_type=authorization_code is supported.',
            );
        }

        $code = $this->stringOrNull($form, 'code');
        if ($code === null) {
            throw new TokenRequestException('invalid_request', 'Missing code.');
        }

        $redirectUri = $this->stringOrNull($form, 'redirect_uri');
        if ($redirectUri === null) {
            throw new TokenRequestException('invalid_request', 'Missing redirect_uri.');
        }

        $codeVerifier = $this->stringOrNull($form, 'code_verifier');
        if ($codeVerifier === null) {
            throw new TokenRequestException('invalid_request', 'Missing code_verifier.');
        }

        return new TokenRequest(
            code: $code,
            redirectUri: $redirectUri,
            codeVerifier: $codeVerifier,
            clientId: $this->stringOrNull($form, 'client_id'),
        );
    }

    /**
     * @param array<string, mixed> $form
     */
    private function stringOrNull(array $form, string $key): ?string
    {
        $value = $form[$key] ?? null;
        if (!\is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}

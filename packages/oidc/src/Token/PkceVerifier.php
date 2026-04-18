<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Token;

/**
 * Verifies a PKCE code_verifier against a stored code_challenge.
 *
 * S256 only (ADR-006 §2.5). Per RFC 7636 §4.6 the challenge is
 * BASE64URL-ENCODE(SHA256(ASCII(code_verifier))) with no padding, and the
 * verifier itself must be 43–128 characters from the unreserved set
 * [A-Z] / [a-z] / [0-9] / "-" / "." / "_" / "~".
 */
final class PkceVerifier
{
    private const METHOD_S256 = 'S256';
    private const MIN_LENGTH = 43;
    private const MAX_LENGTH = 128;
    private const UNRESERVED_PATTERN = '/^[A-Za-z0-9\-._~]+$/';

    public function verify(string $verifier, string $challenge, string $method): bool
    {
        if ($method !== self::METHOD_S256) {
            return false;
        }

        if ($challenge === '' || !$this->isValidVerifier($verifier)) {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }

    private function isValidVerifier(string $verifier): bool
    {
        $length = strlen($verifier);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return false;
        }

        return preg_match(self::UNRESERVED_PATTERN, $verifier) === 1;
    }
}

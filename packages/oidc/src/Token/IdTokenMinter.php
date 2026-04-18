<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Token;

use DateTimeImmutable;
use RuntimeException;
use Waaseyaa\Oidc\Keys\OidcKeyLoaderInterface;
use Waaseyaa\Oidc\Keys\SigningKey;

/**
 * Mints RS256-signed ID tokens per OIDC Core §2 and RFC 7519.
 *
 * Selects the first RS256 signing key that has a private key. ES256 and other
 * algorithms are out of scope for now (ADR-006).
 */
final class IdTokenMinter
{
    private const ALGORITHM = 'RS256';
    private const EXPIRY_SECONDS = 600;

    public function __construct(private readonly OidcKeyLoaderInterface $keyLoader) {}

    public function mint(
        string $issuer,
        string $subject,
        string $audience,
        ?string $nonce,
        DateTimeImmutable $now,
    ): string {
        $key = $this->selectSigningKey();

        $header = [
            'alg' => self::ALGORITHM,
            'typ' => 'JWT',
            'kid' => $key->kid,
        ];

        $iat = $now->getTimestamp();
        $claims = [
            'iss' => $issuer,
            'sub' => $subject,
            'aud' => $audience,
            'exp' => $iat + self::EXPIRY_SECONDS,
            'iat' => $iat,
            'auth_time' => $iat,
        ];
        if ($nonce !== null) {
            $claims['nonce'] = $nonce;
        }

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signingInput = $encodedHeader . '.' . $encodedPayload;

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $key->privateKeyPem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign ID token: ' . openssl_error_string());
        }

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    private function selectSigningKey(): SigningKey
    {
        foreach ($this->keyLoader->loadSigningKeys() as $key) {
            if ($key->algorithm === self::ALGORITHM && $key->privateKeyPem !== null) {
                return $key;
            }
        }

        throw new RuntimeException('No signing key with a private RS256 key is available.');
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}

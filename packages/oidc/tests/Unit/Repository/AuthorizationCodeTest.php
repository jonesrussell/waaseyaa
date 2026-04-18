<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Oidc\Repository\AuthorizationCode;

#[CoversClass(AuthorizationCode::class)]
final class AuthorizationCodeTest extends TestCase
{
    public function testExposesConstructorFields(): void
    {
        $code = new AuthorizationCode(
            code: 'abc123',
            clientId: 'minoo-web',
            accountId: '42',
            redirectUri: 'https://minoo.test/callback',
            scopes: ['openid', 'profile'],
            codeChallenge: 'Ch4lleNg3',
            codeChallengeMethod: 'S256',
            issuedAt: 1_000_000,
            expiresAt: 1_000_060,
            consumedAt: null,
            nonce: 'n-0S6_WzA2Mj',
        );

        self::assertSame('abc123', $code->code);
        self::assertSame('minoo-web', $code->clientId);
        self::assertSame('42', $code->accountId);
        self::assertSame('https://minoo.test/callback', $code->redirectUri);
        self::assertSame(['openid', 'profile'], $code->scopes);
        self::assertSame('Ch4lleNg3', $code->codeChallenge);
        self::assertSame('S256', $code->codeChallengeMethod);
        self::assertSame(1_000_000, $code->issuedAt);
        self::assertSame(1_000_060, $code->expiresAt);
        self::assertNull($code->consumedAt);
        self::assertSame('n-0S6_WzA2Mj', $code->nonce);
    }

    public function testNonceDefaultsToNull(): void
    {
        $code = $this->sample();

        self::assertNull($code->nonce);
    }

    public function testIsExpiredReturnsFalseBeforeExpiry(): void
    {
        $code = $this->sample(expiresAt: 1_000_060);

        self::assertFalse($code->isExpired(1_000_059));
    }

    public function testIsExpiredReturnsTrueAtExpiry(): void
    {
        $code = $this->sample(expiresAt: 1_000_060);

        self::assertTrue($code->isExpired(1_000_060));
    }

    public function testIsExpiredReturnsTrueAfterExpiry(): void
    {
        $code = $this->sample(expiresAt: 1_000_060);

        self::assertTrue($code->isExpired(1_000_061));
    }

    public function testIsConsumedFalseWhenNull(): void
    {
        $code = $this->sample(consumedAt: null);

        self::assertFalse($code->isConsumed());
    }

    public function testIsConsumedTrueWhenTimestampPresent(): void
    {
        $code = $this->sample(consumedAt: 1_000_050);

        self::assertTrue($code->isConsumed());
    }

    /**
     * @param list<string> $scopes
     */
    private function sample(
        string $code = 'abc123',
        string $clientId = 'minoo-web',
        string $accountId = '42',
        string $redirectUri = 'https://minoo.test/callback',
        array $scopes = ['openid'],
        string $codeChallenge = 'challenge',
        string $codeChallengeMethod = 'S256',
        int $issuedAt = 1_000_000,
        int $expiresAt = 1_000_060,
        ?int $consumedAt = null,
    ): AuthorizationCode {
        return new AuthorizationCode(
            code: $code,
            clientId: $clientId,
            accountId: $accountId,
            redirectUri: $redirectUri,
            scopes: $scopes,
            codeChallenge: $codeChallenge,
            codeChallengeMethod: $codeChallengeMethod,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            consumedAt: $consumedAt,
        );
    }
}

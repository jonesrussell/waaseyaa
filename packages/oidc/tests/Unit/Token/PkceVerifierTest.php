<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Oidc\Token\PkceVerifier;

#[CoversClass(PkceVerifier::class)]
final class PkceVerifierTest extends TestCase
{
    // RFC 7636 §4.6 example pair.
    private const RFC_VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
    private const RFC_CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

    #[Test]
    public function verifiesMatchingS256Pair(): void
    {
        $verifier = new PkceVerifier();

        self::assertTrue($verifier->verify(self::RFC_VERIFIER, self::RFC_CHALLENGE, 'S256'));
    }

    #[Test]
    public function rejectsMismatchedChallenge(): void
    {
        $verifier = new PkceVerifier();

        self::assertFalse($verifier->verify(self::RFC_VERIFIER, 'wrong-challenge-value', 'S256'));
    }

    #[Test]
    public function rejectsMismatchedVerifier(): void
    {
        $verifier = new PkceVerifier();

        // Valid-length verifier but wrong content.
        $wrong = str_repeat('a', 43);

        self::assertFalse($verifier->verify($wrong, self::RFC_CHALLENGE, 'S256'));
    }

    #[Test]
    public function rejectsPlainMethod(): void
    {
        $verifier = new PkceVerifier();

        // Even if verifier == challenge, plain method is not supported.
        self::assertFalse($verifier->verify(self::RFC_VERIFIER, self::RFC_VERIFIER, 'plain'));
    }

    #[Test]
    public function rejectsUnknownMethod(): void
    {
        $verifier = new PkceVerifier();

        self::assertFalse($verifier->verify(self::RFC_VERIFIER, self::RFC_CHALLENGE, 'S384'));
    }

    #[Test]
    public function rejectsEmptyMethod(): void
    {
        $verifier = new PkceVerifier();

        self::assertFalse($verifier->verify(self::RFC_VERIFIER, self::RFC_CHALLENGE, ''));
    }

    #[Test]
    public function rejectsVerifierTooShort(): void
    {
        $verifier = new PkceVerifier();

        // 42 chars — one below RFC 7636 minimum of 43.
        $short = str_repeat('a', 42);

        self::assertFalse($verifier->verify($short, self::RFC_CHALLENGE, 'S256'));
    }

    #[Test]
    public function rejectsVerifierTooLong(): void
    {
        $verifier = new PkceVerifier();

        // 129 chars — one above RFC 7636 maximum of 128.
        $long = str_repeat('a', 129);

        self::assertFalse($verifier->verify($long, self::RFC_CHALLENGE, 'S256'));
    }

    #[Test]
    public function acceptsVerifierAtMinLength(): void
    {
        $verifier = new PkceVerifier();

        // 43 chars of 'a' — valid format, mismatched challenge (we're testing length boundary only).
        $minVerifier = str_repeat('a', 43);
        $minChallenge = $this->computeS256Challenge($minVerifier);

        self::assertTrue($verifier->verify($minVerifier, $minChallenge, 'S256'));
    }

    #[Test]
    public function acceptsVerifierAtMaxLength(): void
    {
        $verifier = new PkceVerifier();

        $maxVerifier = str_repeat('a', 128);
        $maxChallenge = $this->computeS256Challenge($maxVerifier);

        self::assertTrue($verifier->verify($maxVerifier, $maxChallenge, 'S256'));
    }

    #[Test]
    public function rejectsVerifierWithInvalidCharacters(): void
    {
        $verifier = new PkceVerifier();

        // 43 chars but contains a '+' — outside RFC 7636 unreserved set [A-Z][a-z][0-9]-._~
        $invalid = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEj+k';
        self::assertSame(43, strlen($invalid));

        self::assertFalse($verifier->verify($invalid, self::RFC_CHALLENGE, 'S256'));
    }

    #[Test]
    public function rejectsEmptyVerifier(): void
    {
        $verifier = new PkceVerifier();

        self::assertFalse($verifier->verify('', self::RFC_CHALLENGE, 'S256'));
    }

    #[Test]
    public function rejectsEmptyChallenge(): void
    {
        $verifier = new PkceVerifier();

        self::assertFalse($verifier->verify(self::RFC_VERIFIER, '', 'S256'));
    }

    private function computeS256Challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}

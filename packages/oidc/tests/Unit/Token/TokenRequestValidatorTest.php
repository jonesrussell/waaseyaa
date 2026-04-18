<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Oidc\Token\TokenRequestException;
use Waaseyaa\Oidc\Token\TokenRequestValidator;

#[CoversClass(TokenRequestValidator::class)]
#[CoversClass(TokenRequestException::class)]
final class TokenRequestValidatorTest extends TestCase
{
    private const VALID_FORM = [
        'grant_type' => 'authorization_code',
        'code' => 'abc123',
        'redirect_uri' => 'https://app.example/callback',
        'code_verifier' => 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
        'client_id' => 'first-party-spa',
    ];

    #[Test]
    public function acceptsWellFormedRequest(): void
    {
        $validator = new TokenRequestValidator();

        $request = $validator->validate(self::VALID_FORM);

        self::assertSame('abc123', $request->code);
        self::assertSame('https://app.example/callback', $request->redirectUri);
        self::assertSame('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk', $request->codeVerifier);
        self::assertSame('first-party-spa', $request->clientId);
    }

    #[Test]
    public function clientIdIsOptionalForBasicAuthPath(): void
    {
        $validator = new TokenRequestValidator();

        $form = self::VALID_FORM;
        unset($form['client_id']);

        $request = $validator->validate($form);

        self::assertNull($request->clientId);
    }

    #[Test]
    public function rejectsMissingGrantType(): void
    {
        $form = self::VALID_FORM;
        unset($form['grant_type']);

        $this->expectValidation('invalid_request', 'grant_type', $form);
    }

    #[Test]
    public function rejectsUnsupportedGrantType(): void
    {
        $form = self::VALID_FORM;
        $form['grant_type'] = 'client_credentials';

        $this->expectValidation('unsupported_grant_type', 'authorization_code', $form);
    }

    #[Test]
    public function rejectsMissingCode(): void
    {
        $form = self::VALID_FORM;
        unset($form['code']);

        $this->expectValidation('invalid_request', 'code', $form);
    }

    #[Test]
    public function rejectsEmptyCode(): void
    {
        $form = self::VALID_FORM;
        $form['code'] = '';

        $this->expectValidation('invalid_request', 'code', $form);
    }

    #[Test]
    public function rejectsMissingRedirectUri(): void
    {
        $form = self::VALID_FORM;
        unset($form['redirect_uri']);

        $this->expectValidation('invalid_request', 'redirect_uri', $form);
    }

    #[Test]
    public function rejectsMissingCodeVerifier(): void
    {
        $form = self::VALID_FORM;
        unset($form['code_verifier']);

        $this->expectValidation('invalid_request', 'code_verifier', $form);
    }

    #[Test]
    public function rejectsNonStringValues(): void
    {
        $form = self::VALID_FORM;
        $form['code'] = ['array', 'not', 'string'];

        $this->expectValidation('invalid_request', 'code', $form);
    }

    /**
     * @param array<string, mixed> $form
     */
    private function expectValidation(string $errorCode, string $mustContain, array $form): void
    {
        try {
            (new TokenRequestValidator())->validate($form);
            self::fail('Expected TokenRequestException');
        } catch (TokenRequestException $e) {
            self::assertSame($errorCode, $e->errorCode);
            self::assertStringContainsString($mustContain, $e->errorDescription);
        }
    }
}

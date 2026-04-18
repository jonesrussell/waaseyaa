<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit\Authorize;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Oidc\Authorize\AuthorizationRequestException;
use Waaseyaa\Oidc\Authorize\AuthorizationRequestValidator;
use Waaseyaa\Oidc\Authorize\ValidatedAuthorizationRequest;
use Waaseyaa\Oidc\Entity\OidcClient;

#[CoversClass(AuthorizationRequestValidator::class)]
#[CoversClass(AuthorizationRequestException::class)]
#[CoversClass(ValidatedAuthorizationRequest::class)]
final class AuthorizationRequestValidatorTest extends TestCase
{
    private AuthorizationRequestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AuthorizationRequestValidator();
    }

    public function testReturnsValidatedRequestOnValidInput(): void
    {
        $result = $this->validator->validate($this->newClient(), $this->validQuery());

        $this->assertInstanceOf(ValidatedAuthorizationRequest::class, $result);
        $this->assertSame('minoo-web', $result->client->getClientId());
        $this->assertSame('https://minoo.test/callback', $result->redirectUri);
        $this->assertSame(['openid', 'profile'], $result->scopes);
        $this->assertSame('xyz-state', $result->state);
        $this->assertSame('a-challenge', $result->codeChallenge);
        $this->assertSame('S256', $result->codeChallengeMethod);
        $this->assertNull($result->nonce);
    }

    public function testNoncePassedThrough(): void
    {
        $query = $this->validQuery();
        $query['nonce'] = 'nonce-abc';

        $result = $this->validator->validate($this->newClient(), $query);

        $this->assertSame('nonce-abc', $result->nonce);
    }

    public function testStateIsOptionalOnSuccess(): void
    {
        $query = $this->validQuery();
        unset($query['state']);

        $result = $this->validator->validate($this->newClient(), $query);

        $this->assertNull($result->state);
    }

    public function testRedirectUriMissingThrowsDirectError(): void
    {
        $query = $this->validQuery();
        unset($query['redirect_uri']);

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertNull($e->redirectUri, 'Direct error must not carry redirect_uri.');
        }
    }

    public function testRedirectUriNotRegisteredThrowsDirectError(): void
    {
        $query = $this->validQuery();
        $query['redirect_uri'] = 'https://evil.test/cb';

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertNull($e->redirectUri);
        }
    }

    public function testMissingResponseTypeThrowsRedirectableError(): void
    {
        $query = $this->validQuery();
        unset($query['response_type']);

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('unsupported_response_type', $e->errorCode);
            $this->assertSame('https://minoo.test/callback', $e->redirectUri);
            $this->assertSame('xyz-state', $e->state);
        }
    }

    public function testUnsupportedResponseTypeThrowsRedirectableError(): void
    {
        $query = $this->validQuery();
        $query['response_type'] = 'token';

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('unsupported_response_type', $e->errorCode);
            $this->assertSame('https://minoo.test/callback', $e->redirectUri);
        }
    }

    public function testMissingScopeThrowsInvalidScope(): void
    {
        $query = $this->validQuery();
        unset($query['scope']);

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('invalid_scope', $e->errorCode);
            $this->assertSame('https://minoo.test/callback', $e->redirectUri);
        }
    }

    public function testScopeWithoutOpenidThrowsInvalidScope(): void
    {
        $query = $this->validQuery();
        $query['scope'] = 'profile email';

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('invalid_scope', $e->errorCode);
        }
    }

    public function testScopeWithDisallowedValueThrowsInvalidScope(): void
    {
        $query = $this->validQuery();
        $query['scope'] = 'openid admin-all';

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('invalid_scope', $e->errorCode);
            $this->assertSame('https://minoo.test/callback', $e->redirectUri);
        }
    }

    public function testMissingCodeChallengeThrowsInvalidRequest(): void
    {
        $query = $this->validQuery();
        unset($query['code_challenge']);

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame('https://minoo.test/callback', $e->redirectUri);
        }
    }

    public function testMissingCodeChallengeMethodThrowsInvalidRequest(): void
    {
        $query = $this->validQuery();
        unset($query['code_challenge_method']);

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('invalid_request', $e->errorCode);
        }
    }

    public function testUnsupportedCodeChallengeMethodThrowsInvalidRequest(): void
    {
        $query = $this->validQuery();
        $query['code_challenge_method'] = 'plain';

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame('https://minoo.test/callback', $e->redirectUri);
        }
    }

    public function testStatePreservedOnRedirectableError(): void
    {
        $query = $this->validQuery();
        $query['response_type'] = 'token';
        $query['state'] = 'preserved-state';

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertSame('preserved-state', $e->state);
        }
    }

    public function testStateNullWhenAbsentOnRedirectableError(): void
    {
        $query = $this->validQuery();
        unset($query['state']);
        $query['response_type'] = 'token';

        try {
            $this->validator->validate($this->newClient(), $query);
            $this->fail('Expected AuthorizationRequestException.');
        } catch (AuthorizationRequestException $e) {
            $this->assertNull($e->state);
        }
    }

    private function newClient(): OidcClient
    {
        return new OidcClient(values: [
            'client_id' => 'minoo-web',
            'name' => 'Minoo',
            'redirect_uris' => ['https://minoo.test/callback'],
            'scopes' => ['openid', 'profile', 'email'],
            'grant_types' => ['authorization_code'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validQuery(): array
    {
        return [
            'client_id' => 'minoo-web',
            'redirect_uri' => 'https://minoo.test/callback',
            'response_type' => 'code',
            'scope' => 'openid profile',
            'state' => 'xyz-state',
            'code_challenge' => 'a-challenge',
            'code_challenge_method' => 'S256',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Oidc\Entity\OidcClient;

#[CoversClass(OidcClient::class)]
final class OidcClientTest extends TestCase
{
    public function testExtendsContentEntityBase(): void
    {
        $client = new OidcClient();
        $this->assertInstanceOf(ContentEntityBase::class, $client);
    }

    public function testEntityTypeId(): void
    {
        $client = new OidcClient();
        $this->assertSame('oidc_client', $client->getEntityTypeId());
    }

    public function testNewClientHasNoId(): void
    {
        $client = new OidcClient();
        $this->assertNull($client->id());
    }

    public function testNewClientIsNew(): void
    {
        $client = new OidcClient();
        $this->assertTrue($client->isNew());
    }

    public function testClientWithIdIsNotNew(): void
    {
        $client = new OidcClient(['id' => 7]);
        $this->assertSame(7, $client->id());
        $this->assertFalse($client->isNew());
    }

    public function testAutoGeneratesUuid(): void
    {
        $client = new OidcClient();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $client->uuid(),
        );
    }

    public function testExplicitUuidIsPreserved(): void
    {
        $client = new OidcClient(['uuid' => 'my-uuid']);
        $this->assertSame('my-uuid', $client->uuid());
    }

    public function testLabelReturnsName(): void
    {
        $client = new OidcClient(['name' => 'Minoo']);
        $this->assertSame('Minoo', $client->label());
    }

    public function testClientIdGetterSetter(): void
    {
        $client = new OidcClient();
        $client->setClientId('minoo-web');
        $this->assertSame('minoo-web', $client->getClientId());
    }

    public function testClientIdFromValues(): void
    {
        $client = new OidcClient(['client_id' => 'biindigen']);
        $this->assertSame('biindigen', $client->getClientId());
    }

    public function testRedirectUrisGetterSetter(): void
    {
        $client = new OidcClient();
        $client->setRedirectUris(['https://minoo.test/callback', 'https://minoo.test/silent']);
        $this->assertSame(
            ['https://minoo.test/callback', 'https://minoo.test/silent'],
            $client->getRedirectUris(),
        );
    }

    public function testRedirectUrisDefaultsToEmptyArray(): void
    {
        $client = new OidcClient();
        $this->assertSame([], $client->getRedirectUris());
    }

    public function testHasRedirectUriExactMatch(): void
    {
        $client = new OidcClient([
            'redirect_uris' => ['https://minoo.test/callback'],
        ]);
        $this->assertTrue($client->hasRedirectUri('https://minoo.test/callback'));
    }

    public function testHasRedirectUriRejectsTrailingSlashVariant(): void
    {
        $client = new OidcClient([
            'redirect_uris' => ['https://minoo.test/callback'],
        ]);
        // Exact match only — OIDC spec requires byte-for-byte match.
        $this->assertFalse($client->hasRedirectUri('https://minoo.test/callback/'));
    }

    public function testHasRedirectUriRejectsCaseVariant(): void
    {
        $client = new OidcClient([
            'redirect_uris' => ['https://minoo.test/Callback'],
        ]);
        $this->assertFalse($client->hasRedirectUri('https://minoo.test/callback'));
    }

    public function testScopesGetterSetter(): void
    {
        $client = new OidcClient();
        $client->setScopes(['openid', 'profile', 'email']);
        $this->assertSame(['openid', 'profile', 'email'], $client->getScopes());
    }

    public function testScopesDefaultsToOpenid(): void
    {
        $client = new OidcClient();
        $this->assertSame(['openid'], $client->getScopes());
    }

    public function testHasScope(): void
    {
        $client = new OidcClient(['scopes' => ['openid', 'profile']]);
        $this->assertTrue($client->hasScope('openid'));
        $this->assertTrue($client->hasScope('profile'));
        $this->assertFalse($client->hasScope('email'));
    }

    public function testGrantTypesGetterSetter(): void
    {
        $client = new OidcClient();
        $client->setGrantTypes(['authorization_code', 'refresh_token']);
        $this->assertSame(['authorization_code', 'refresh_token'], $client->getGrantTypes());
    }

    public function testGrantTypesDefaultsToAuthorizationCode(): void
    {
        $client = new OidcClient();
        $this->assertSame(['authorization_code'], $client->getGrantTypes());
    }

    public function testSupportsGrantType(): void
    {
        $client = new OidcClient([
            'grant_types' => ['authorization_code', 'refresh_token'],
        ]);
        $this->assertTrue($client->supportsGrantType('authorization_code'));
        $this->assertFalse($client->supportsGrantType('client_credentials'));
    }

    public function testIsConfidentialDefaultsFalse(): void
    {
        // Public clients (SPAs, mobile) are the default for OIDC + PKCE.
        $client = new OidcClient();
        $this->assertFalse($client->isConfidential());
    }

    public function testIsConfidentialGetterSetter(): void
    {
        $client = new OidcClient();
        $client->setConfidential(true);
        $this->assertTrue($client->isConfidential());
    }

    public function testClientSecretHashNullByDefault(): void
    {
        $client = new OidcClient();
        $this->assertNull($client->getClientSecretHash());
    }

    public function testClientSecretHashGetterSetter(): void
    {
        $client = new OidcClient();
        $client->setClientSecretHash('hashed-secret-value');
        $this->assertSame('hashed-secret-value', $client->getClientSecretHash());
    }

    public function testNameGetterSetter(): void
    {
        $client = new OidcClient();
        $client->setName('Minoo Web App');
        $this->assertSame('Minoo Web App', $client->getName());
    }
}

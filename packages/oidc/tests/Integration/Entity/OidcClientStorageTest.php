<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Integration\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Oidc\Entity\OidcClient;

/**
 * Full CRUD lifecycle for OidcClient against a real in-memory SQLite database.
 *
 * Verifies that the entity's default values (scopes, grant_types), array fields
 * (redirect_uris), and scalar fields (client_id, client_secret_hash) all round-trip
 * cleanly through SqlEntityStorage.
 */
#[CoversClass(OidcClient::class)]
final class OidcClientStorageTest extends TestCase
{
    private DBALDatabase $database;
    private SqlEntityStorage $storage;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite();

        $entityType = new EntityType(
            id: 'oidc_client',
            label: 'OIDC Client',
            class: OidcClient::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
        );

        $schemaHandler = new SqlSchemaHandler($entityType, $this->database);
        $schemaHandler->ensureTable();

        // client_id gets an explicit indexed column — every authorize request
        // looks up clients by this field, so it must not live in the _data blob.
        $schemaHandler->addFieldColumns([
            'client_id' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
            'name' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
            'is_confidential' => ['type' => 'int', 'not null' => true, 'default' => 0],
            'client_secret_hash' => ['type' => 'varchar', 'length' => 255, 'not null' => false],
        ]);

        $this->storage = new SqlEntityStorage(
            $entityType,
            $this->database,
            new EventDispatcher(),
        );
    }

    public function testCreateAndSaveAssignsId(): void
    {
        $client = $this->storage->create([
            'client_id' => 'minoo-web',
            'name' => 'Minoo',
            'redirect_uris' => ['https://minoo.test/callback'],
        ]);

        $this->storage->save($client);

        $this->assertNotNull($client->id());
        $this->assertIsInt($client->id());
        $this->assertFalse($client->isNew());
    }

    public function testDefaultsAreAppliedOnCreate(): void
    {
        $client = $this->storage->create([
            'client_id' => 'minoo-web',
            'name' => 'Minoo',
        ]);

        $this->assertSame(['openid'], $client->getScopes());
        $this->assertSame(['authorization_code'], $client->getGrantTypes());
        $this->assertFalse($client->isConfidential());
        $this->assertSame([], $client->getRedirectUris());
    }

    public function testFullRoundTripPreservesAllFields(): void
    {
        $client = $this->storage->create([
            'client_id' => 'biindigen',
            'name' => 'Biindigen Community Portal',
            'redirect_uris' => [
                'https://biindigen.test/auth/callback',
                'https://biindigen.test/auth/silent',
            ],
            'scopes' => ['openid', 'profile', 'email'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => true,
            'client_secret_hash' => 'hashed-secret',
        ]);
        $this->storage->save($client);
        $id = $client->id();
        $uuid = $client->uuid();

        $loaded = $this->storage->load($id);

        $this->assertNotNull($loaded);
        $this->assertInstanceOf(OidcClient::class, $loaded);
        $this->assertSame($id, $loaded->id());
        $this->assertSame($uuid, $loaded->uuid());
        $this->assertSame('biindigen', $loaded->getClientId());
        $this->assertSame('Biindigen Community Portal', $loaded->getName());
        $this->assertSame(
            ['https://biindigen.test/auth/callback', 'https://biindigen.test/auth/silent'],
            $loaded->getRedirectUris(),
        );
        $this->assertSame(['openid', 'profile', 'email'], $loaded->getScopes());
        $this->assertSame(['authorization_code', 'refresh_token'], $loaded->getGrantTypes());
        $this->assertTrue($loaded->isConfidential());
        $this->assertSame('hashed-secret', $loaded->getClientSecretHash());
    }

    public function testUpdatePersistsChanges(): void
    {
        $client = $this->storage->create([
            'client_id' => 'minoo-web',
            'name' => 'Minoo',
            'redirect_uris' => ['https://minoo.test/callback'],
        ]);
        $this->storage->save($client);
        $id = $client->id();

        $client->setRedirectUris([
            'https://minoo.test/callback',
            'https://minoo.test/new-callback',
        ]);
        $client->setScopes(['openid', 'profile']);
        $this->storage->save($client);

        $reloaded = $this->storage->load($id);
        $this->assertSame(
            ['https://minoo.test/callback', 'https://minoo.test/new-callback'],
            $reloaded->getRedirectUris(),
        );
        $this->assertSame(['openid', 'profile'], $reloaded->getScopes());
    }

    public function testUpdatePreservesUuid(): void
    {
        $client = $this->storage->create([
            'client_id' => 'minoo-web',
            'name' => 'Minoo',
        ]);
        $this->storage->save($client);
        $originalUuid = $client->uuid();

        $client->setName('Minoo (renamed)');
        $this->storage->save($client);

        $loaded = $this->storage->load($client->id());
        $this->assertSame($originalUuid, $loaded->uuid());
    }

    public function testQueryByClientId(): void
    {
        $this->seedClients();

        $ids = $this->storage->getQuery()
            ->condition('client_id', 'biindigen')
            ->execute();

        $this->assertCount(1, $ids);
        $client = $this->storage->load($ids[0]);
        $this->assertSame('biindigen', $client->getClientId());
    }

    public function testDeleteRemovesClient(): void
    {
        $this->seedClients();
        $all = $this->storage->getQuery()->execute();
        $this->assertCount(2, $all);

        $toDelete = $this->storage->load($all[0]);
        $this->storage->delete([$toDelete]);

        $remaining = $this->storage->getQuery()->execute();
        $this->assertCount(1, $remaining);
        $this->assertNull($this->storage->load($all[0]));
    }

    public function testFreshStorageLoadsSameEntity(): void
    {
        $client = $this->storage->create([
            'client_id' => 'minoo-web',
            'name' => 'Minoo',
            'scopes' => ['openid', 'profile'],
        ]);
        $this->storage->save($client);
        $id = $client->id();

        $fresh = new SqlEntityStorage(
            new EntityType(
                id: 'oidc_client',
                label: 'OIDC Client',
                class: OidcClient::class,
                keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
            ),
            $this->database,
            new EventDispatcher(),
        );

        $loaded = $fresh->load($id);
        $this->assertNotNull($loaded);
        $this->assertSame('minoo-web', $loaded->getClientId());
        $this->assertSame(['openid', 'profile'], $loaded->getScopes());
    }

    private function seedClients(): void
    {
        $seeds = [
            ['client_id' => 'minoo-web', 'name' => 'Minoo'],
            ['client_id' => 'biindigen', 'name' => 'Biindigen'],
        ];
        foreach ($seeds as $values) {
            $client = $this->storage->create($values);
            $this->storage->save($client);
        }
    }
}

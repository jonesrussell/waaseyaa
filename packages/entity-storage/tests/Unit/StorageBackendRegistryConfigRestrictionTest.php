<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Backend\BackendRestrictionEnforcer;
use Waaseyaa\Config\Exception\InvalidConfigBackendException;
use Waaseyaa\Config\Tests\Unit\Backend\Fixtures\FakeConfigEntity;
use Waaseyaa\Config\Tests\Unit\Backend\Fixtures\FakeNonConfigEntity;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\StorageBackendRegistry;

#[CoversClass(StorageBackendRegistry::class)]
final class StorageBackendRegistryConfigRestrictionTest extends TestCase
{
    #[Test]
    public function registersConfigEntityOnSqlBlob(): void
    {
        $registry = new StorageBackendRegistry();
        $type = $this->makeConfigEntityType('fake_config', ReservedBackendIds::SQL_BLOB);

        $registry->register($type);

        self::assertSame(ReservedBackendIds::SQL_BLOB, $registry->backendFor('fake_config'));
        self::assertTrue($registry->has('fake_config'));
    }

    #[Test]
    public function registersConfigEntityOnSqlColumn(): void
    {
        $registry = new StorageBackendRegistry();
        $type = $this->makeConfigEntityType('fake_config_col', ReservedBackendIds::SQL_COLUMN);

        $registry->register($type);

        self::assertSame(ReservedBackendIds::SQL_COLUMN, $registry->backendFor('fake_config_col'));
    }

    #[Test]
    public function resolvesNullPrimaryBackendToSqlBlobDefault(): void
    {
        $registry = new StorageBackendRegistry();
        $type = $this->makeConfigEntityType('fake_config_default', null);

        $registry->register($type);

        self::assertSame(
            ReservedBackendIds::SQL_BLOB,
            $registry->backendFor('fake_config_default'),
            'null primaryStorageBackend must resolve to the framework default (sql-blob).',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenBackendsForConfigEntities(): iterable
    {
        yield 'vector backend is forbidden' => [ReservedBackendIds::VECTOR];
        yield 'remote backend is forbidden' => ['remote'];
        yield 'unknown future backend is forbidden' => ['quantum-7'];
    }

    #[Test]
    #[DataProvider('forbiddenBackendsForConfigEntities')]
    public function refusesConfigEntityOnForbiddenBackendAtRegistrationTime(string $backendId): void
    {
        $registry = new StorageBackendRegistry();
        $type = $this->makeConfigEntityType('fake_config_bad', $backendId);

        try {
            $registry->register($type);
            self::fail('Expected InvalidConfigBackendException at registration time.');
        } catch (InvalidConfigBackendException $exception) {
            self::assertSame('fake_config_bad', $exception->getEntityTypeId());
            self::assertSame($backendId, $exception->getBackendId());
            self::assertSame(FakeConfigEntity::class, $exception->getDeclaringFqcn());
        }
    }

    #[Test]
    public function permitsNonConfigEntityOnAnyBackend(): void
    {
        $registry = new StorageBackendRegistry();
        $type = $this->makeContentEntityType('fake_content_vec', ReservedBackendIds::VECTOR);

        $registry->register($type);

        self::assertSame(ReservedBackendIds::VECTOR, $registry->backendFor('fake_content_vec'));
    }

    #[Test]
    public function validateAllRerunsTheGateOverEveryRegistration(): void
    {
        // Build the enforcer and registry so we can record + replay validation.
        $registry = new StorageBackendRegistry(new BackendRestrictionEnforcer());

        $registry->register($this->makeConfigEntityType('a', ReservedBackendIds::SQL_BLOB));
        $registry->register($this->makeConfigEntityType('b', ReservedBackendIds::SQL_COLUMN));

        // Idempotent: second pass over the same state is a no-op.
        $registry->validateAll();
        $registry->validateAll();

        self::assertSame(
            ['a' => ReservedBackendIds::SQL_BLOB, 'b' => ReservedBackendIds::SQL_COLUMN],
            $registry->all(),
        );
    }

    #[Test]
    public function backendForReturnsNullForUnknownEntityType(): void
    {
        $registry = new StorageBackendRegistry();

        self::assertNull($registry->backendFor('not_registered'));
        self::assertFalse($registry->has('not_registered'));
    }

    #[Test]
    public function reservedIdsAndEnforcerAllowListAgree(): void
    {
        $reserved = ReservedBackendIds::all();

        foreach (BackendRestrictionEnforcer::ALLOWED_BACKEND_IDS as $allowed) {
            self::assertContains(
                $allowed,
                $reserved,
                \sprintf(
                    'Enforcer allow-list "%s" must be one of the reserved backend ids (%s).',
                    $allowed,
                    implode(', ', $reserved),
                ),
            );
        }
    }

    private function makeConfigEntityType(string $id, ?string $primaryBackend): EntityTypeInterface
    {
        return new EntityType(
            id: $id,
            label: $id,
            class: FakeConfigEntity::class,
            primaryStorageBackend: $primaryBackend,
        );
    }

    private function makeContentEntityType(string $id, ?string $primaryBackend): EntityTypeInterface
    {
        return new EntityType(
            id: $id,
            label: $id,
            class: FakeNonConfigEntity::class,
            primaryStorageBackend: $primaryBackend,
        );
    }
}

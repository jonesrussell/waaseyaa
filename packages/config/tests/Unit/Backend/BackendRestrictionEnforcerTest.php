<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Config\Backend\BackendRestrictionEnforcer;
use Waaseyaa\Config\Exception\InvalidConfigBackendException;
use Waaseyaa\Config\Tests\Unit\Backend\Fixtures\FakeConfigEntity;
use Waaseyaa\Config\Tests\Unit\Backend\Fixtures\FakeNonConfigEntity;

#[CoversClass(BackendRestrictionEnforcer::class)]
final class BackendRestrictionEnforcerTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedBackendsProvider(): iterable
    {
        yield 'sql-blob is allowed' => ['sql-blob'];
        yield 'sql-column is allowed' => ['sql-column'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function deniedBackendsProvider(): iterable
    {
        yield 'vector is forbidden' => ['vector'];
        yield 'remote is forbidden' => ['remote'];
        yield 'unknown future backend is forbidden' => ['quantum-sql-7'];
        yield 'empty string is forbidden' => [''];
    }

    #[Test]
    #[DataProvider('allowedBackendsProvider')]
    public function allowsConfigEntityOnPermittedBackend(string $backendId): void
    {
        $enforcer = new BackendRestrictionEnforcer();

        $enforcer->validate(
            entityTypeId: 'fake_config',
            declaringFqcn: FakeConfigEntity::class,
            backendId: $backendId,
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[DataProvider('deniedBackendsProvider')]
    public function rejectsConfigEntityOnForbiddenBackend(string $backendId): void
    {
        $enforcer = new BackendRestrictionEnforcer();

        try {
            $enforcer->validate(
                entityTypeId: 'fake_config',
                declaringFqcn: FakeConfigEntity::class,
                backendId: $backendId,
            );
            self::fail('Expected InvalidConfigBackendException was not raised.');
        } catch (InvalidConfigBackendException $exception) {
            self::assertSame('fake_config', $exception->getEntityTypeId());
            self::assertSame($backendId, $exception->getBackendId());
            self::assertSame(FakeConfigEntity::class, $exception->getDeclaringFqcn());
        }
    }

    #[Test]
    #[DataProvider('deniedBackendsProvider')]
    public function skipsNonConfigEntityTypesEvenOnForbiddenBackend(string $backendId): void
    {
        $enforcer = new BackendRestrictionEnforcer();

        $enforcer->validate(
            entityTypeId: 'fake_content',
            declaringFqcn: FakeNonConfigEntity::class,
            backendId: $backendId,
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skipsClassesThatCannotBeAutoloaded(): void
    {
        $enforcer = new BackendRestrictionEnforcer();

        // Should not throw — unknown classes are treated as "not a config
        // entity"; the registry is the authoritative class-existence guard.
        $enforcer->validate(
            entityTypeId: 'phantom',
            declaringFqcn: 'Acme\\Phantom\\DoesNotExist',
            backendId: 'vector',
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function isConfigEntityClassReturnsFalseForEmptyFqcn(): void
    {
        $enforcer = new BackendRestrictionEnforcer();

        self::assertFalse($enforcer->isConfigEntityClass(''));
    }

    #[Test]
    public function isConfigEntityClassReturnsTrueForConfigSubclass(): void
    {
        $enforcer = new BackendRestrictionEnforcer();

        self::assertTrue($enforcer->isConfigEntityClass(FakeConfigEntity::class));
    }

    #[Test]
    public function isConfigEntityClassReturnsFalseForNonConfigSubclass(): void
    {
        $enforcer = new BackendRestrictionEnforcer();

        self::assertFalse($enforcer->isConfigEntityClass(FakeNonConfigEntity::class));
    }

    #[Test]
    public function allowedBackendIdsContractMatchesPolicy(): void
    {
        self::assertSame(
            ['sql-blob', 'sql-column'],
            BackendRestrictionEnforcer::ALLOWED_BACKEND_IDS,
            'sql-blob and sql-column are the only permitted backends for config entities (FR-044).',
        );
    }
}

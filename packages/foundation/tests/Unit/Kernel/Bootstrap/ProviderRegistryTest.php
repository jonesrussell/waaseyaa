<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Kernel\Bootstrap;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\DefinesEntityType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Exception\EntityTypeRegistrationCollisionException;
use Waaseyaa\Foundation\Attribute\AsEntityType;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Kernel\Bootstrap\ProviderRegistry;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\LoggerTrait;
use Waaseyaa\Foundation\Log\LogLevel;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Regression test for issue #3: service provider DI cannot resolve
 * kernel-provided services (EntityTypeManager, DatabaseInterface, etc.).
 */
#[CoversClass(ProviderRegistry::class)]
final class ProviderRegistryTest extends TestCase
{
    #[Test]
    public function provider_can_resolve_kernel_services_via_fallback(): void
    {
        $registry = new ProviderRegistry(new NullLogger());
        $database = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher();
        $entityTypeManager = new EntityTypeManager($dispatcher);

        $manifest = new PackageManifest(
            providers: [KernelResolverTestProvider::class],
        );

        $providers = $registry->discoverAndRegister(
            manifest: $manifest,
            projectRoot: sys_get_temp_dir(),
            config: [],
            entityTypeManager: $entityTypeManager,
            database: $database,
            dispatcher: $dispatcher,
        );

        $this->assertCount(1, $providers);

        $resolved = $providers[0]->resolve(EntityTypeManager::class);
        $this->assertSame($entityTypeManager, $resolved);
    }

    #[Test]
    public function provider_can_resolve_cross_provider_bindings(): void
    {
        $registry = new ProviderRegistry(new NullLogger());
        $database = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher();
        $entityTypeManager = new EntityTypeManager($dispatcher);

        $manifest = new PackageManifest(
            providers: [
                CrossProviderSourceProvider::class,
                CrossProviderConsumerProvider::class,
            ],
        );

        $providers = $registry->discoverAndRegister(
            manifest: $manifest,
            projectRoot: sys_get_temp_dir(),
            config: [],
            entityTypeManager: $entityTypeManager,
            database: $database,
            dispatcher: $dispatcher,
        );

        $this->assertCount(2, $providers);

        $resolved = $providers[1]->resolve('test.cross_provider_service');
        $this->assertInstanceOf(\stdClass::class, $resolved);
        $this->assertSame('from-source', $resolved->origin);
    }

    #[Test]
    public function missing_provider_warning_includes_remediation_guidance(): void
    {
        $logger = new class implements LoggerInterface {
            use LoggerTrait;

            /** @var list<array{level: LogLevel, message: string}> */
            public array $messages = [];

            public function log(LogLevel $level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = ['level' => $level, 'message' => (string) $message];
            }
        };

        $registry = new ProviderRegistry($logger);
        $database = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher();
        $entityTypeManager = new EntityTypeManager($dispatcher);

        $manifest = new PackageManifest(
            providers: ['App\\Provider\\NonexistentProvider'],
        );

        $registry->discoverAndRegister(
            manifest: $manifest,
            projectRoot: sys_get_temp_dir(),
            config: [],
            entityTypeManager: $entityTypeManager,
            database: $database,
            dispatcher: $dispatcher,
        );

        $warnings = array_filter($logger->messages, fn ($message) => $message['level'] === LogLevel::WARNING);
        $this->assertNotEmpty($warnings);

        $warning = array_values($warnings)[0]['message'];
        $this->assertStringContainsString('NonexistentProvider', $warning);
        $this->assertStringContainsString('optimize:manifest', $warning);
        $this->assertStringContainsString('composer.json', $warning);
    }

    #[Test]
    public function duplicate_entity_type_registration_throws_collision_exception_and_stops_boot(): void
    {
        $registry = new ProviderRegistry(new NullLogger());
        $database = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher();
        $entityTypeManager = new EntityTypeManager($dispatcher);

        $manifest = new PackageManifest(
            providers: [
                DuplicateEntityTypeSourceProvider::class,
                DuplicateEntityTypeConsumerProvider::class,
            ],
        );

        $this->expectException(EntityTypeRegistrationCollisionException::class);
        $this->expectExceptionMessage('[ENTITY_TYPE_DUPLICATE]');
        $this->expectExceptionMessage(DuplicateEntityTypeSourceProvider::class);
        $this->expectExceptionMessage(DuplicateEntityTypeConsumerProvider::class);
        $this->expectExceptionMessage(EntityTypeFixture::class);

        $registry->discoverAndRegister(
            manifest: $manifest,
            projectRoot: sys_get_temp_dir(),
            config: [],
            entityTypeManager: $entityTypeManager,
            database: $database,
            dispatcher: $dispatcher,
        );
    }

    #[Test]
    public function entity_auto_register_registers_attribute_manifest_classes(): void
    {
        $registry = new ProviderRegistry(new NullLogger());
        $database = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher();
        $entityTypeManager = new EntityTypeManager($dispatcher);

        $manifest = new PackageManifest(
            providers: [],
            attributeEntityTypes: [AttributeAutoEntityFixture::class],
        );

        $registry->discoverAndRegister(
            manifest: $manifest,
            projectRoot: sys_get_temp_dir(),
            config: ['entity_auto_register' => true],
            entityTypeManager: $entityTypeManager,
            database: $database,
            dispatcher: $dispatcher,
        );

        $this->assertTrue($entityTypeManager->hasDefinition('attr_auto_fixture'));
    }

    #[Test]
    public function entity_auto_register_off_skips_attribute_manifest_classes(): void
    {
        $registry = new ProviderRegistry(new NullLogger());
        $database = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher();
        $entityTypeManager = new EntityTypeManager($dispatcher);

        $manifest = new PackageManifest(
            providers: [],
            attributeEntityTypes: [AttributeAutoEntityFixture::class],
        );

        $registry->discoverAndRegister(
            manifest: $manifest,
            projectRoot: sys_get_temp_dir(),
            config: [],
            entityTypeManager: $entityTypeManager,
            database: $database,
            dispatcher: $dispatcher,
        );

        $this->assertFalse($entityTypeManager->hasDefinition('attr_auto_fixture'));
    }
}

/**
 * @internal Test fixture provider that resolves EntityTypeManager from kernel.
 */
final class KernelResolverTestProvider extends ServiceProvider
{
    public function register(): void
    {
        // No local bindings; EntityTypeManager must come from kernel resolver.
    }
}

/**
 * @internal Test fixture provider that registers a binding for cross-provider resolution.
 */
final class CrossProviderSourceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton('test.cross_provider_service', static function (): \stdClass {
            $obj = new \stdClass();
            $obj->origin = 'from-source';

            return $obj;
        });
    }
}

/**
 * @internal Test fixture provider that consumes a binding from another provider.
 */
final class CrossProviderConsumerProvider extends ServiceProvider
{
    public function register(): void
    {
        // No local bindings; depends on CrossProviderSourceProvider's binding.
    }
}

final class DuplicateEntityTypeSourceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'group',
            label: 'Group',
            class: EntityTypeFixture::class,
        ));
    }
}

final class DuplicateEntityTypeConsumerProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'group',
            label: 'Group duplicate',
            class: EntityTypeFixture::class,
        ));
    }
}

final class EntityTypeFixture {}

#[AsEntityType(id: 'attr_auto_fixture', label: 'Attr fixture')]
final class AttributeAutoEntityFixture implements DefinesEntityType
{
    public static function entityType(): EntityType
    {
        return new EntityType(
            id: 'attr_auto_fixture',
            label: 'Attr fixture',
            class: self::class,
        );
    }
}

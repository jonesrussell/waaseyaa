<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;

#[CoversClass(EntityTypeManager::class)]
final class EntityTypeManagerBundleFieldsTest extends TestCase
{
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    #[Test]
    public function addBundleFieldsDelegatesToRegistryWhenEntityTypeIsMultiBundle(): void
    {
        $registry = new SpyRegistry();
        $manager = new EntityTypeManager($this->dispatcher, null, null, $registry);
        $manager->registerEntityType(new EntityType(
            id: 'group',
            label: 'Group',
            class: TestEntity::class,
            bundleEntityType: 'group_type',
        ));

        $fakeField = new \stdClass();

        $manager->addBundleFields('group', 'business', ['email' => $fakeField]);

        self::assertSame(
            [['group', 'business', ['email' => $fakeField]]],
            $registry->bundleCalls,
        );
    }

    #[Test]
    public function addBundleFieldsThrowsWhenNoRegistryConfigured(): void
    {
        $manager = new EntityTypeManager($this->dispatcher);
        $manager->registerEntityType(new EntityType(
            id: 'group',
            label: 'Group',
            class: TestEntity::class,
            bundleEntityType: 'group_type',
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no FieldDefinitionRegistry configured');

        $manager->addBundleFields('group', 'business', []);
    }

    #[Test]
    public function addBundleFieldsThrowsForUnregisteredEntityType(): void
    {
        $registry = new SpyRegistry();
        $manager = new EntityTypeManager($this->dispatcher, null, null, $registry);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity type "group" is not registered.');

        $manager->addBundleFields('group', 'business', []);
    }

    #[Test]
    public function addBundleFieldsThrowsForEntityTypeWithoutBundleEntityType(): void
    {
        $registry = new SpyRegistry();
        $manager = new EntityTypeManager($this->dispatcher, null, null, $registry);
        $manager->registerEntityType(new EntityType(
            id: 'user',
            label: 'User',
            class: TestEntity::class,
        ));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not declare bundleEntityType');

        $manager->addBundleFields('user', 'business', []);
    }

    #[Test]
    public function registerEntityTypePushesCoreFieldsIntoRegistry(): void
    {
        $registry = new SpyRegistry();
        $manager = new EntityTypeManager($this->dispatcher, null, null, $registry);

        $coreFieldMeta = ['name' => ['type' => 'string']];
        $manager->registerEntityType(new EntityType(
            id: 'group',
            label: 'Group',
            class: TestEntity::class,
            bundleEntityType: 'group_type',
            fieldDefinitions: $coreFieldMeta,
        ));

        self::assertSame(
            [['group', $coreFieldMeta]],
            $registry->coreCalls,
        );
    }

    #[Test]
    public function getFieldRegistryReturnsRegistryOrThrows(): void
    {
        $registry = new SpyRegistry();
        $withRegistry = new EntityTypeManager($this->dispatcher, null, null, $registry);
        self::assertSame($registry, $withRegistry->getFieldRegistry());

        $withoutRegistry = new EntityTypeManager($this->dispatcher);
        $this->expectException(\RuntimeException::class);
        $withoutRegistry->getFieldRegistry();
    }
}

/**
 * In-memory spy registry used only by this test.
 */
final class SpyRegistry implements FieldDefinitionRegistryInterface
{
    /** @var list<array{0: string, 1: array<string, mixed>}> */
    public array $coreCalls = [];

    /** @var list<array{0: string, 1: string, 2: array<string|int, object>}> */
    public array $bundleCalls = [];

    public function registerCoreFields(string $entityTypeId, array $fields): void
    {
        $this->coreCalls[] = [$entityTypeId, $fields];
    }

    public function mergeCoreFields(string $entityTypeId, array $fields): void
    {
        $this->coreCalls[] = [$entityTypeId, $fields];
    }

    public function registerBundleFields(string $entityTypeId, string $bundle, array $fields): void
    {
        $this->bundleCalls[] = [$entityTypeId, $bundle, $fields];
    }

    public function coreFieldsFor(string $entityTypeId): array
    {
        foreach ($this->coreCalls as [$id, $fields]) {
            if ($id === $entityTypeId) {
                return $fields;
            }
        }
        return [];
    }

    public function bundleFieldsFor(string $entityTypeId, string $bundle): array
    {
        foreach ($this->bundleCalls as [$id, $b, $fields]) {
            if ($id === $entityTypeId && $b === $bundle) {
                /** @var array<string, object> $fields */
                return $fields;
            }
        }
        return [];
    }

    public function bundleNamesFor(string $entityTypeId): array
    {
        $names = [];
        foreach ($this->bundleCalls as [$id, $b, $_fields]) {
            if ($id === $entityTypeId) {
                $names[$b] = true;
            }
        }
        return \array_keys($names);
    }

    public function bundlesDefiningField(string $entityTypeId, string $fieldName): array
    {
        $bundles = [];
        foreach ($this->bundleCalls as [$id, $b, $fields]) {
            if ($id !== $entityTypeId) {
                continue;
            }
            foreach ($fields as $key => $field) {
                $name = \is_string($key) ? $key : (\is_object($field) && \method_exists($field, 'getName') ? $field->getName() : null);
                if ($name === $fieldName) {
                    $bundles[$b] = true;
                    break;
                }
            }
        }
        return \array_keys($bundles);
    }
}

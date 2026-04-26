<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\Attribute\EntityMetadataReader;
use Waaseyaa\Entity\Exception\EntityTypeRegistrationCollisionException;
use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Registry-based entity type manager.
 *
 * Holds entity type definitions and provides access to storage handlers.
 * Entity types are registered explicitly by service providers; optional
 * attribute-driven registration runs when {@code entity_auto_register} is enabled
 * in application config (see {@see \Waaseyaa\Foundation\Kernel\Bootstrap\ProviderRegistry}).
 */
class EntityTypeManager implements EntityTypeManagerInterface
{
    /**
     * Registered entity type definitions.
     *
     * @var array<string, EntityTypeInterface>
     */
    private array $definitions = [];

    /** @var array<string, class-string|null> */
    private array $definitionRegistrants = [];

    /**
     * Cached storage handler instances.
     *
     * @var array<string, Storage\EntityStorageInterface>
     */
    private array $storageInstances = [];

    /**
     * Cached repository instances.
     *
     * @var array<string, EntityRepositoryInterface>
     */
    private array $repositoryInstances = [];

    /**
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher for entity lifecycle events.
     * @param \Closure|null $storageFactory A factory callable: fn(EntityTypeInterface): EntityStorageInterface.
     *                                     If null, getStorage() will throw when no storage class is configured.
     * @param \Closure|null $repositoryFactory A factory callable: fn(string $entityTypeId, EntityTypeInterface): EntityRepositoryInterface.
     *                                          If null, getRepository() throws.
     * @param FieldDefinitionRegistryInterface|null $fieldRegistry Registry for core and bundle-scoped field definitions.
     *                                                             If null, addBundleFields() and getFieldRegistry() throw.
     *                                                             Core fields are registered into the registry at
     *                                                             registerEntityType() time when this is provided.
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?\Closure $storageFactory = null,
        private readonly ?\Closure $repositoryFactory = null,
        private readonly ?FieldDefinitionRegistryInterface $fieldRegistry = null,
    ) {}

    /**
     * Register an entity type definition.
     *
     * The `core.` namespace is reserved for built-in platform types. Attempting
     * to register a type with a `core.*` ID via this method throws NAMESPACE_RESERVED.
     * Use registerCoreEntityType() for platform-level registrations.
     *
     * @throws \DomainException        If the entity type ID uses the reserved `core.` namespace.
     * @throws \InvalidArgumentException If an entity type with the same ID is already registered.
     */
    public function registerEntityType(EntityTypeInterface $type, ?string $registrant = null): void
    {
        if (str_starts_with($type->id(), 'core.')) {
            throw new \DomainException(\sprintf(
                '[NAMESPACE_RESERVED] The "core." namespace is reserved for built-in platform types. '
                . 'Entity type "%s" cannot be registered by extensions or tenants. '
                . 'Use a custom namespace prefix instead.',
                $type->id(),
            ));
        }

        $this->persistDefinition($type, $registrant);
    }

    /**
     * Register a built-in platform entity type, bypassing the `core.` namespace guard.
     *
     * Only kernel boot code and core service providers should call this method.
     *
     * @throws \InvalidArgumentException If an entity type with the same ID is already registered.
     */
    public function registerCoreEntityType(EntityTypeInterface $type, ?string $registrant = null): void
    {
        $this->persistDefinition($type, $registrant);
    }

    private function persistDefinition(EntityTypeInterface $type, ?string $registrant = null): void
    {
        if (isset($this->definitions[$type->id()])) {
            $existingDefinition = $this->definitions[$type->id()];
            $existingRegistrant = $this->definitionRegistrants[$type->id()] ?? null;

            if ($existingDefinition->getClass() === $type->getClass()) {
                throw EntityTypeRegistrationCollisionException::duplicate(
                    $type->id(),
                    $existingRegistrant,
                    $existingDefinition->getClass(),
                    $registrant,
                    $type->getClass(),
                );
            }

            throw EntityTypeRegistrationCollisionException::shadowCollision(
                $type->id(),
                $existingRegistrant,
                $existingDefinition->getClass(),
                $registrant,
                $type->getClass(),
            );
        }

        $this->assertClassMetadataMatchesEntityType($type);

        $this->definitions[$type->id()] = $type;
        $this->definitionRegistrants[$type->id()] = $registrant;

        $this->fieldRegistry?->registerCoreFields($type->id(), $type->getFieldDefinitions());
    }

    /**
     * Ensures {@see ContentEntityType} / {@see ContentEntityKeys} on the PHP class match the {@see EntityType} definition.
     */
    private function assertClassMetadataMatchesEntityType(EntityTypeInterface $type): void
    {
        $class = $type->getClass();
        if (!class_exists($class) || !is_subclass_of($class, ContentEntityBase::class)) {
            return;
        }

        $meta = EntityMetadataReader::forClass($class);
        if ($meta->typeId === null) {
            throw new \InvalidArgumentException(\sprintf(
                'Entity type "%s" class %s must declare #[ContentEntityType] on a content entity.',
                $type->id(),
                $class,
            ));
        }

        if ($meta->typeId !== $type->id()) {
            throw new \InvalidArgumentException(\sprintf(
                'Entity type id "%s" does not match #[ContentEntityType(id: "%s")] on class %s.',
                $type->id(),
                $meta->typeId,
                $class,
            ));
        }

        $cfg = $type->getKeys();
        $expected = $meta->keys;
        $a = $cfg;
        $b = $expected;
        ksort($a);
        ksort($b);
        if ($a !== $b) {
            throw new \InvalidArgumentException(\sprintf(
                'Entity type "%s" registered keys do not match class metadata for %s.',
                $type->id(),
                $class,
            ));
        }
    }

    /**
     * Register bundle-scoped fields on a multi-bundle entity type.
     *
     * See docs/specs/bundle-scoped-fields.md for the full contract.
     *
     * @param array<string|int, object> $fields FieldDefinition objects whose
     *                                          targetEntityTypeId and targetBundle
     *                                          match the arguments.
     *
     * @throws \InvalidArgumentException If the entity type is not registered, does
     *                                   not declare bundleEntityType, or any field
     *                                   fails validation (see registry).
     * @throws \RuntimeException         If no FieldDefinitionRegistry is configured.
     */
    public function addBundleFields(string $entityTypeId, string $bundle, array $fields): void
    {
        if ($this->fieldRegistry === null) {
            throw new \RuntimeException(\sprintf(
                'Cannot register bundle fields for entity type "%s" bundle "%s": no FieldDefinitionRegistry configured on EntityTypeManager.',
                $entityTypeId,
                $bundle,
            ));
        }

        $type = $this->getDefinition($entityTypeId);

        if ($type->getBundleEntityType() === null) {
            throw new \InvalidArgumentException(\sprintf(
                'Entity type "%s" does not declare bundleEntityType; cannot register bundle-scoped fields.',
                $entityTypeId,
            ));
        }

        $this->fieldRegistry->registerBundleFields($entityTypeId, $bundle, $fields);
    }

    /**
     * Returns the configured FieldDefinitionRegistry.
     *
     * @throws \RuntimeException If no registry was configured.
     */
    public function getFieldRegistry(): FieldDefinitionRegistryInterface
    {
        if ($this->fieldRegistry === null) {
            throw new \RuntimeException('No FieldDefinitionRegistry configured on EntityTypeManager.');
        }

        return $this->fieldRegistry;
    }

    public function getDefinition(string $entityTypeId): EntityTypeInterface
    {
        if (!isset($this->definitions[$entityTypeId])) {
            throw new \InvalidArgumentException(\sprintf(
                'Entity type "%s" is not registered.',
                $entityTypeId,
            ));
        }

        return $this->definitions[$entityTypeId];
    }

    /** @return array<string, EntityTypeInterface> */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    public function hasDefinition(string $entityTypeId): bool
    {
        return isset($this->definitions[$entityTypeId]);
    }

    public function getStorage(string $entityTypeId): Storage\EntityStorageInterface
    {
        if (isset($this->storageInstances[$entityTypeId])) {
            return $this->storageInstances[$entityTypeId];
        }

        $definition = $this->getDefinition($entityTypeId);

        if ($this->storageFactory !== null) {
            $storage = ($this->storageFactory)($definition);
        } else {
            $storageClass = $definition->getStorageClass();

            if ($storageClass === '') {
                throw new \RuntimeException(\sprintf(
                    'No storage class configured for entity type "%s" and no storage factory provided.',
                    $entityTypeId,
                ));
            }

            $storage = new $storageClass();
        }

        if (!$storage instanceof Storage\EntityStorageInterface) {
            throw new \RuntimeException(\sprintf(
                'Storage for entity type "%s" must implement %s.',
                $entityTypeId,
                Storage\EntityStorageInterface::class,
            ));
        }

        $this->storageInstances[$entityTypeId] = $storage;

        return $storage;
    }

    public function getRepository(string $entityTypeId): EntityRepositoryInterface
    {
        if (isset($this->repositoryInstances[$entityTypeId])) {
            return $this->repositoryInstances[$entityTypeId];
        }

        if ($this->repositoryFactory === null) {
            throw new \RuntimeException(\sprintf(
                'No repository factory configured for EntityTypeManager; cannot build repository for entity type "%s".',
                $entityTypeId,
            ));
        }

        $definition = $this->getDefinition($entityTypeId);
        $repository = ($this->repositoryFactory)($entityTypeId, $definition);

        if (!$repository instanceof EntityRepositoryInterface) {
            throw new \RuntimeException(\sprintf(
                'Repository factory for entity type "%s" must return an instance of %s.',
                $entityTypeId,
                EntityRepositoryInterface::class,
            ));
        }

        $this->repositoryInstances[$entityTypeId] = $repository;

        return $repository;
    }

    /**
     * Get the event dispatcher.
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }
}

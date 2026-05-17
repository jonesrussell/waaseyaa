<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Waaseyaa\Config\Backend\BackendRestrictionEnforcer;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;

/**
 * @api
 *
 * Boot-time registry that records each entity type's resolved storage backend
 * and runs the config-entity backend-restriction gate (FR-044..FR-046).
 *
 * The registry sits at the boundary between {@see EntityTypeInterface}
 * (which knows its preferred backend via `getPrimaryStorageBackend()`) and
 * the framework default. It resolves `null` to {@see ReservedBackendIds::SQL_BLOB},
 * stores the (entity-type-id => backend-id) mapping, and — for any entity
 * type whose class implements {@see \Waaseyaa\Entity\ConfigEntityInterface} —
 * delegates to {@see BackendRestrictionEnforcer::validate()} so that boot
 * fails loudly when a config entity declares a non-SQL backend.
 *
 * The class is intentionally side-effect-light: `register()` stores +
 * validates; `validateAll()` re-runs the gate over the recorded set so
 * callers (kernel boot, tests) can defer validation until after every
 * entity type has been registered.
 */
final class StorageBackendRegistry
{
    /** @var array<string, string> entity-type id => resolved backend id */
    private array $backends = [];

    /** @var array<string, string> entity-type id => declaring class FQCN */
    private array $declaringClasses = [];

    public function __construct(
        private readonly BackendRestrictionEnforcer $enforcer = new BackendRestrictionEnforcer(),
    ) {}

    /**
     * Register an entity type's resolved backend choice.
     *
     * Resolves a `null` primary backend to the framework default
     * ({@see ReservedBackendIds::SQL_BLOB}) and immediately runs the
     * config-entity gate. Re-registering the same id overwrites the
     * previous record so kernel rebuilds and tests remain idempotent.
     *
     * @throws \Waaseyaa\Config\Exception\InvalidConfigBackendException
     *   When `$entityType` is a config entity and its resolved backend is
     *   not one of `sql-blob`/`sql-column`.
     */
    public function register(EntityTypeInterface $entityType): void
    {
        $backendId = $entityType->getPrimaryStorageBackend() ?? ReservedBackendIds::SQL_BLOB;
        $entityTypeId = $entityType->id();
        $declaringFqcn = $entityType->getClass();

        $this->backends[$entityTypeId] = $backendId;
        $this->declaringClasses[$entityTypeId] = $declaringFqcn;

        $this->enforcer->validate($entityTypeId, $declaringFqcn, $backendId);
    }

    /**
     * Re-run the config-entity gate over every entity type recorded so far.
     *
     * Useful when registration order is not deterministic: callers may
     * register all entity types first and call `validateAll()` once at
     * the end of boot.
     */
    public function validateAll(): void
    {
        foreach ($this->backends as $entityTypeId => $backendId) {
            $this->enforcer->validate(
                $entityTypeId,
                $this->declaringClasses[$entityTypeId],
                $backendId,
            );
        }
    }

    /**
     * Return the resolved backend id for an entity type, or null if unregistered.
     */
    public function backendFor(string $entityTypeId): ?string
    {
        return $this->backends[$entityTypeId] ?? null;
    }

    /**
     * Return all recorded entity-type → backend-id mappings.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->backends;
    }

    public function has(string $entityTypeId): bool
    {
        return isset($this->backends[$entityTypeId]);
    }
}

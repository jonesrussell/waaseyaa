<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Exception;

/**
 * @api
 *
 * Boot-time exception raised when a config entity declares a storage backend
 * outside the allowed set (`sql-blob`, `sql-column`).
 *
 * Per ADR 010 + ADR 018 and FR-044..FR-046, config entities MUST persist on
 * a SQL backend so that the active/sync store split and the YAML export
 * pipeline remain deterministic. Backends such as `vector`, `remote`, or any
 * unregistered future backend are not permitted for config entity types.
 *
 * The exception carries:
 *  - the offending entity-type id (e.g. `node_type`),
 *  - the disallowed backend id (e.g. `vector`),
 *  - the FQCN of the declaring code (e.g. `App\Entity\MyConfigEntity`).
 *
 * It is raised by {@see \Waaseyaa\Config\Backend\BackendRestrictionEnforcer}
 * and wired into the boot-time path via
 * `\Waaseyaa\EntityStorage\StorageBackendRegistry`.
 */
final class InvalidConfigBackendException extends \RuntimeException
{
    /**
     * @param string $entityTypeId Machine-name id of the offending entity type.
     * @param string $backendId Disallowed backend id the entity type declared.
     * @param string $declaringFqcn Fully-qualified class name of the declaring code.
     * @param list<string> $allowedBackendIds Backend ids that ARE permitted for config entities.
     */
    public function __construct(
        private readonly string $entityTypeId,
        private readonly string $backendId,
        private readonly string $declaringFqcn,
        private readonly array $allowedBackendIds,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            \sprintf(
                'Config entity type "%s" (declared by %s) requests storage backend "%s", '
                . 'which is not permitted for config entities. Config entities may only use '
                . 'one of: [%s]. See ADR 010 + ADR 018; remove the override or move this '
                . 'entity off the config-entity contract.',
                $this->entityTypeId,
                $this->declaringFqcn,
                $this->backendId,
                implode(', ', $this->allowedBackendIds),
            ),
            0,
            $previous,
        );
    }

    public function getEntityTypeId(): string
    {
        return $this->entityTypeId;
    }

    public function getBackendId(): string
    {
        return $this->backendId;
    }

    public function getDeclaringFqcn(): string
    {
        return $this->declaringFqcn;
    }

    /**
     * @return list<string>
     */
    public function getAllowedBackendIds(): array
    {
        return $this->allowedBackendIds;
    }
}

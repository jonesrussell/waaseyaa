<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Backend;

use Waaseyaa\Config\Exception\InvalidConfigBackendException;

/**
 * @api
 *
 * Boot-time validator that ensures config entity types only use the
 * `sql-blob` or `sql-column` storage backends (FR-044..FR-046, ADR 010, ADR 018).
 *
 * The enforcer is stateless and works with scalar inputs (entity-type id,
 * declaring FQCN, resolved backend id) to keep the config package independent
 * of the entity package's type API. {@see \Waaseyaa\EntityStorage\StorageBackendRegistry}
 * is the canonical call-site: it extracts these scalars from
 * {@see \Waaseyaa\Entity\EntityTypeInterface} and invokes
 * {@see self::validate()} during boot, before request handling.
 *
 * A class is considered a "config entity" when its FQCN implements
 * `\Waaseyaa\Entity\ConfigEntityInterface` (checked via reflection so the
 * config package does not need a runtime use-statement on Layer 1 entity
 * types).
 */
final class BackendRestrictionEnforcer
{
    /**
     * String FQN avoids a runtime use-statement on the entity package so
     * the enforcer stays usable even if `waaseyaa/entity` is not explicitly
     * required by `waaseyaa/config`. `is_subclass_of()` accepts strings.
     *
     * @internal
     */
    public const string CONFIG_ENTITY_INTERFACE = 'Waaseyaa\\Entity\\ConfigEntityInterface';

    /**
     * Backend ids that may host config entity types.
     *
     * Mirrored from `\Waaseyaa\EntityStorage\Backend\ReservedBackendIds` so
     * the enforcer remains usable without a runtime dep on entity-storage;
     * the {@see StorageBackendRegistryConfigRestrictionTest} verifies the
     * two lists stay in sync.
     *
     * @var list<string>
     */
    public const array ALLOWED_BACKEND_IDS = ['sql-blob', 'sql-column'];

    /**
     * Validate that a config entity type's backend choice is permitted.
     *
     * Non-config entity types (classes that do not implement
     * {@see \Waaseyaa\Entity\ConfigEntityInterface}) are skipped: this gate
     * only constrains the config contract.
     *
     * Unknown / not-yet-loaded classes are skipped silently to keep the
     * boot path resilient — {@see StorageBackendRegistry} performs its own
     * class-existence check and is the authoritative call-site.
     *
     * @param string $entityTypeId Machine-name id of the entity type.
     * @param string $declaringFqcn Fully-qualified class name of the entity class.
     * @param string $backendId Backend id the type would resolve to.
     *
     * @throws InvalidConfigBackendException When `$declaringFqcn` is a config entity
     *   and `$backendId` is outside {@see self::ALLOWED_BACKEND_IDS}.
     */
    public function validate(string $entityTypeId, string $declaringFqcn, string $backendId): void
    {
        if (!$this->isConfigEntityClass($declaringFqcn)) {
            return;
        }

        if (in_array($backendId, self::ALLOWED_BACKEND_IDS, true)) {
            return;
        }

        throw new InvalidConfigBackendException(
            entityTypeId: $entityTypeId,
            backendId: $backendId,
            declaringFqcn: $declaringFqcn,
            allowedBackendIds: self::ALLOWED_BACKEND_IDS,
        );
    }

    /**
     * Whether a FQCN implements the config entity contract.
     *
     * Returns false for classes that cannot be autoloaded; the registry is
     * responsible for guarding against missing classes earlier in the boot
     * sequence.
     */
    public function isConfigEntityClass(string $fqcn): bool
    {
        if ($fqcn === '') {
            return false;
        }

        if (!class_exists($fqcn) && !interface_exists($fqcn)) {
            return false;
        }

        return is_subclass_of($fqcn, self::CONFIG_ENTITY_INTERFACE);
    }
}

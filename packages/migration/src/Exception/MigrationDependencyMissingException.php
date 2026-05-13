<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Exception;

/**
 * Thrown when {@see \Waaseyaa\Migration\Discovery\MigrationRegistry::boot()}
 * encounters a migration whose `dependencies[]` list names an id that is not
 * registered in the registry (FR-014).
 *
 * The exception carries both the missing dependency id and the requesting
 * migration id so operators can resolve the typo or registration gap without
 * grepping the source tree.
 *
 * The framework refuses to boot when this is raised — running a migration
 * whose declared prerequisite never ran is unsafe.
 *
 * @api
 */
final class MigrationDependencyMissingException extends \LogicException
{
    /**
     * @param string $missingDependencyId The migration id declared in `dependencies[]` that is not registered.
     * @param string $requestingMigrationId The migration that named the missing dependency.
     *
     * @throws \InvalidArgumentException When either id is empty.
     */
    public function __construct(
        public readonly string $missingDependencyId,
        public readonly string $requestingMigrationId,
    ) {
        if ($missingDependencyId === '') {
            throw new \InvalidArgumentException(
                'MigrationDependencyMissingException::$missingDependencyId must be a non-empty string.',
            );
        }
        if ($requestingMigrationId === '') {
            throw new \InvalidArgumentException(
                'MigrationDependencyMissingException::$requestingMigrationId must be a non-empty string.',
            );
        }

        parent::__construct(\sprintf(
            'Migration %s declares dependency on %s, which is not registered.',
            \var_export($requestingMigrationId, true),
            \var_export($missingDependencyId, true),
        ), code: 0);
    }
}

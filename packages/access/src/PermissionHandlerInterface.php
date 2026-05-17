<?php

declare(strict_types=1);

namespace Waaseyaa\Access;

/**
 * Manages the registry of available permissions.
 * @api
 */
interface PermissionHandlerInterface
{
    /**
     * Returns all registered permissions.
     *
     * @return array<string, array{title: string, description: string}>
     *   Keyed by permission ID.
     */
    public function getPermissions(): array;

    /**
     * Whether a permission with the given ID is defined.
     */
    public function hasPermission(string $permission): bool;
}

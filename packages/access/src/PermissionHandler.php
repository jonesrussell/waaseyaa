<?php

declare(strict_types=1);

namespace Waaseyaa\Access;

/**
 * Simple in-memory registry of permissions.
 */
final class PermissionHandler implements PermissionHandlerInterface
{
    /**
     * @var array<string, array{title: string, description: string}>
     */
    private array $permissions = [];

    /**
     * Register a new permission.
     *
     * @param string $id          Machine name (e.g. 'create article').
     * @param string $title       Human-readable title.
     * @param string $description Optional description.
     */
    public function registerPermission(string $id, string $title, string $description = ''): void
    {
        $this->permissions[$id] = [
            'title' => $title,
            'description' => $description,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPermission(string $permission): bool
    {
        return isset($this->permissions[$permission]);
    }
}

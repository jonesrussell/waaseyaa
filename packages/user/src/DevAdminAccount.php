<?php

declare(strict_types=1);

namespace Waaseyaa\User;

use Waaseyaa\Access\AccountInterface;

/**
 * Dev-only admin account with all permissions.
 *
 * Used as a fallback when running under PHP's built-in server (cli-server)
 * and no user is identified in the session. Wired only when
 * PHP_SAPI === 'cli-server' in public/index.php.
 *
 * MUST NOT be used in production.
 */
final class DevAdminAccount implements AccountInterface
{
    public function __construct()
    {
        if (PHP_SAPI !== 'cli-server' && PHP_SAPI !== 'cli') {
            throw new \LogicException(
                'DevAdminAccount must only be used with PHP built-in server (cli-server SAPI). '
                . 'Current SAPI: ' . PHP_SAPI,
            );
        }
    }

    public function id(): int
    {
        return PHP_INT_MAX;
    }

    public function hasPermission(string $permission): bool
    {
        return true;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return ['administrator'];
    }

    public function isAuthenticated(): bool
    {
        return true;
    }
}

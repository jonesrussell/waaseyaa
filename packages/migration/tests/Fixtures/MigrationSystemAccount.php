<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Fixtures;

use Waaseyaa\Access\AccountInterface;

/**
 * Minimal AccountInterface fixture standing in for a migration runner's system account.
 *
 * Used by {@see \Waaseyaa\Migration\Tests\Integration\EntityDestinationTest}
 * and {@see \Waaseyaa\Migration\Tests\Integration\EntityDestinationRevisionsTest}.
 *
 * @internal Test fixture only.
 */
final class MigrationSystemAccount implements AccountInterface
{
    public function id(): int|string
    {
        return 'system-migration';
    }

    public function hasPermission(string $permission): bool
    {
        return true;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return ['system'];
    }

    public function isAuthenticated(): bool
    {
        return true;
    }
}

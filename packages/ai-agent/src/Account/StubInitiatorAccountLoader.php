<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Account;

use Waaseyaa\Access\AccountInterface;

/**
 * Default {@see InitiatorAccountLoaderInterface} implementation: returns
 * a minimal authenticated account carrying just the id.
 *
 * Has no permissions and no roles beyond `authenticated`. The executor's
 * tool-call access checks still flow through `AgentTool::execute()`, so
 * apps that require permission semantics MUST wire a real loader.
 *
 * @api
 */
final class StubInitiatorAccountLoader implements InitiatorAccountLoaderInterface
{
    public function load(int|string $accountId): AccountInterface
    {
        return new class ($accountId) implements AccountInterface {
            public function __construct(private readonly int|string $accountId) {}

            public function id(): int|string
            {
                return $this->accountId;
            }

            public function hasPermission(string $permission): bool
            {
                return false;
            }

            public function getRoles(): array
            {
                return ['authenticated'];
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };
    }
}

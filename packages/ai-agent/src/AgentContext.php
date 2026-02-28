<?php

declare(strict_types=1);

namespace Aurora\AI\Agent;

use Aurora\Access\AccountInterface;

/**
 * Context passed to agents during execution.
 *
 * Contains the user account the agent acts as, agent-specific parameters,
 * and whether this is a dry run.
 */
final readonly class AgentContext
{
    /**
     * @param AccountInterface $account The user the agent acts as
     * @param array<string, mixed> $parameters Agent-specific parameters
     * @param bool $dryRun Whether this is a dry run
     */
    public function __construct(
        public AccountInterface $account,
        public array $parameters = [],
        public bool $dryRun = false,
    ) {}
}

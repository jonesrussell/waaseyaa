<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Broadcast;

use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Override the WP-04 baseline {@see BroadcastStorageAdapter} binding with
 * the canonical {@see AgentRunBroadcaster}.
 *
 * The kernel resolves the *last* binding for a given abstract; this
 * provider MUST be registered after {@see \Waaseyaa\AI\Agent\MessagingServiceProvider}
 * so the rebind wins (composer.json provider order).
 *
 * Kept as its own provider so WP-04's `MessagingServiceProvider` stays
 * a stable WP-04 artifact: rebinding here documents WP-05 ownership
 * of the broadcaster.
 *
 * @api
 */
final class AgentRunBroadcasterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(
            AgentRunBroadcasterInterface::class,
            fn(): AgentRunBroadcasterInterface => new AgentRunBroadcaster(
                new BroadcastStorage($this->resolve(DatabaseInterface::class)),
                $this->safeResolve(LoggerInterface::class) ?? new NullLogger(),
            ),
        );
    }

    private function safeResolve(string $abstract): mixed
    {
        try {
            return $this->resolve($abstract);
        } catch (\Throwable) {
            return null;
        }
    }
}

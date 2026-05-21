<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Broadcast;

use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Binds {@see AgentRunBroadcasterInterface} → {@see AgentRunBroadcaster}.
 *
 * Registered after {@see \Waaseyaa\AI\Agent\MessagingServiceProvider} in
 * `composer.json` so its singleton is the one the kernel resolves.
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

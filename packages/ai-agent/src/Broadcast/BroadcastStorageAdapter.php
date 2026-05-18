<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Broadcast;

use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * WP-04 baseline implementation of {@see AgentRunBroadcasterInterface}.
 *
 * Writes events directly through the existing {@see BroadcastStorage}
 * SSE log so the worker can emit `run_*`/`tool_call_*` events without
 * waiting on WP-05's full broadcaster. The L5 → L4 import (ai-agent
 * → api) is a downward dependency, allowed by the layer rule.
 *
 * Failures are swallowed and logged: SSE delivery is a "best-effort
 * side effect" per the constitution — a broken broadcast channel
 * must never crash an agent run.
 *
 * @api
 */
final class BroadcastStorageAdapter implements AgentRunBroadcasterInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly BroadcastStorage $storage,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function push(string $runId, string $event, array $data): void
    {
        $channel = 'agent.run.' . $runId;
        $payload = ['run_id' => $runId] + $data;

        try {
            $this->storage->push($channel, $event, $payload);
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf(
                'BroadcastStorageAdapter: failed to push "%s" for run %s: %s',
                $event,
                $runId,
                $e->getMessage(),
            ));
        }
    }
}

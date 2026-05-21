<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Broadcast;

use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * Canonical {@see AgentRunBroadcasterInterface} implementation.
 *
 * Writes events through {@see BroadcastStorage} onto the SSE channel
 * `agent.run.<runId>`, shaped per the data-model § "SSE event vocabulary".
 *
 * NFR-006: the push is synchronous — `BroadcastStorage::push()` persists
 * to the broadcast log inside this call, so callers can rely on the
 * event being durable before they advance the worker loop.
 *
 * Failures are best-effort: a broken broadcast surface MUST NOT crash
 * an in-flight run, so we swallow the exception and log it (constitution
 * gotcha "Best-effort side effects").
 *
 * @api
 */
final class AgentRunBroadcaster implements AgentRunBroadcasterInterface
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
                'AgentRunBroadcaster: failed to push "%s" for run %s: %s',
                $event,
                $runId,
                $e->getMessage(),
            ));
        }
    }
}

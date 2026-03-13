<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Recorder;

use Waaseyaa\Telescope\Storage\TelescopeStoreInterface;

/**
 * Records codified context session lifecycle events (start and end).
 */
final class CodifiedContextSessionRecorder
{
    public const string TYPE = 'cc_session';

    public function __construct(
        private readonly TelescopeStoreInterface $store,
    ) {}

    public function recordStart(string $sessionId, string $repoHash, array $metadata = []): void
    {
        $this->store->store(self::TYPE, [
            'session_id' => $sessionId,
            'event_type' => 'session_start',
            'repo_hash' => $repoHash,
            'metadata' => $metadata,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function recordEnd(string $sessionId, float $durationMs, int $eventCount): void
    {
        $this->store->store(self::TYPE, [
            'session_id' => $sessionId,
            'event_type' => 'session_end',
            'duration_ms' => $durationMs,
            'event_count' => $eventCount,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }
}

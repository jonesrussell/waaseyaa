<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class SessionEnded
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $sessionId,
        public readonly float $durationMs,
        public readonly int $eventCount,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'duration_ms' => $this->durationMs,
            'event_count' => $this->eventCount,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class ValidationCompleted
{
    public readonly \DateTimeImmutable $occurredAt;

    /**
     * @param array<int, string> $issues
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly int $driftScore,
        public readonly array $issues,
        public readonly string $recommendation,
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
            'drift_score' => $this->driftScore,
            'issues' => $this->issues,
            'recommendation' => $this->recommendation,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

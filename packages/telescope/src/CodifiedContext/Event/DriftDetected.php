<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class DriftDetected
{
    public readonly \DateTimeImmutable $occurredAt;

    /**
     * @param array<int, string> $issues
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly int $driftScore,
        public readonly string $severity,
        public readonly array $issues,
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
            'severity' => $this->severity,
            'issues' => $this->issues,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

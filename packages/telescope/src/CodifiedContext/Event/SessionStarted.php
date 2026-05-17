<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class SessionStarted
{
    public readonly \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $repoHash,
        public readonly array $metadata = [],
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
            'repo_hash' => $this->repoHash,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

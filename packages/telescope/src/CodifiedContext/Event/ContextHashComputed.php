<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class ContextHashComputed
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $contextHash,
        public readonly string $algorithm = 'sha256',
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
            'context_hash' => $this->contextHash,
            'algorithm' => $this->algorithm,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

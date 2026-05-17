<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class ModelOutputRecorded
{
    public readonly \DateTimeImmutable $occurredAt;

    /**
     * @param array<int, string> $references
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $outputHash,
        public readonly array $references,
        public readonly int $tokenCount,
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
            'output_hash' => $this->outputHash,
            'references' => $this->references,
            'token_count' => $this->tokenCount,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

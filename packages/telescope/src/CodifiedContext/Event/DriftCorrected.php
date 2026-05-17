<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class DriftCorrected
{
    public readonly \DateTimeImmutable $occurredAt;

    /**
     * @param array<int, string> $corrections
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly int $originalScore,
        public readonly int $correctedScore,
        public readonly array $corrections,
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
            'original_score' => $this->originalScore,
            'corrected_score' => $this->correctedScore,
            'corrections' => $this->corrections,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

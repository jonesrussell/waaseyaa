<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class ContextLoaded
{
    public readonly \DateTimeImmutable $occurredAt;

    /**
     * @param array<int, string> $filePaths
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $contextHash,
        public readonly array $filePaths,
        public readonly int $totalBytes,
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
            'file_paths' => $this->filePaths,
            'total_bytes' => $this->totalBytes,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

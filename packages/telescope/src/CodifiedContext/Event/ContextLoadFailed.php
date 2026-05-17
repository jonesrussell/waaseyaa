<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Event;

/**
 * @api
 */
final class ContextLoadFailed
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $errorMessage,
        public readonly ?string $filePath,
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
            'error_message' => $this->errorMessage,
            'file_path' => $this->filePath,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

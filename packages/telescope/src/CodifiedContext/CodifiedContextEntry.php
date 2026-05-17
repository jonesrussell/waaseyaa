<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext;

/**
 * Value object representing a single recorded codified context entry.
 * @api
 */
final class CodifiedContextEntry
{
    public readonly string $id;
    public readonly \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data,
        public readonly string $sessionId,
        ?string $id = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->id = $id ?? bin2hex(random_bytes(16));
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'session_id' => $this->sessionId,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s.u'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            type: $row['type'],
            data: is_string($row['data']) ? json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR) : $row['data'],
            sessionId: $row['session_id'],
            id: $row['id'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Api\CodifiedContext;

/**
 * API-layer value object for a codified-context session row (decoupled from Telescope storage types).
 */
final readonly class CodifiedContextSessionRow
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
        public string $sessionId,
        public \DateTimeImmutable $createdAt,
    ) {}
}

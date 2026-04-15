<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Value;

final readonly class Outcome
{
    public const STATUSES = ['accepted', 'rejected', 'modified'];

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $status,
        public ?string $feedback = null,
        public array $metadata = [],
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Outcome status must be one of ' . implode(', ', self::STATUSES) . ', got ' . $status,
            );
        }
    }
}

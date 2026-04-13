<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Policy;

use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

final readonly class GuardrailRule
{
    public function __construct(
        public string $operation,
        public SovereigntyProfile $deniedProfile,
        public string $reason,
    ) {}

    /** @return array{operation: string, denied_profile: string, reason: string} */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'denied_profile' => $this->deniedProfile->value,
            'reason' => $this->reason,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Value;

final readonly class DecisionTrace
{
    /** @param string[] $alternatives */
    public function __construct(
        public string $question,
        public string $chosen,
        public array $alternatives,
        public string $reasoning,
        public float $confidence,
    ) {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException(
                'DecisionTrace confidence must be in [0.0, 1.0], got ' . $confidence,
            );
        }
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'question' => $this->question,
            'chosen' => $this->chosen,
            'alternatives' => $this->alternatives,
            'reasoning' => $this->reasoning,
            'confidence' => $this->confidence,
        ];
    }
}

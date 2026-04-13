<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Graph;

final readonly class GraphSection
{
    public function __construct(
        public string $key,
        public string $version,
        public array $data,
    ) {}

    /** @return array{key: string, version: string, data: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'version' => $this->version,
            'data' => $this->data,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp;

final readonly class McpServerCard
{
    public function __construct(
        private string $name = 'Waaseyaa',
        private string $version = '0.1.0',
        private string $endpoint = '/mcp',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'description' => 'AI-native content management system',
            'endpoint' => $this->endpoint,
            'transport' => 'streamable-http',
            'capabilities' => [
                'tools' => true,
                'resources' => false,
                'prompts' => false,
            ],
            'authentication' => [
                'type' => 'bearer',
            ],
        ];
    }

    public function toJson(): string
    {
        return \json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
    }
}

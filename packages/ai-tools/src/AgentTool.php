<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Tools;

/**
 * Runtime value object representing a tool registered with the
 * {@see \Waaseyaa\AI\Tools\ToolRegistryInterface}.
 *
 * Pairs the `#[AsAgentTool]` attribute payload with the concrete
 * {@see AgentToolInterface} implementation resolved from the container.
 *
 * @api
 */
final readonly class AgentTool
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema draft 2020-12
     */
    public function __construct(
        public string $name,
        public string $capability,
        public bool $destructive,
        public bool $dryRunSupported,
        public string $category,
        public array $inputSchema,
        public AgentToolInterface $impl,
    ) {}
}

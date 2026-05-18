<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Attribute;

use Waaseyaa\AI\Agent\Enum\HitlMode;

/**
 * Marks a class as an agent definition discoverable via
 * {@see \Waaseyaa\Foundation\Discovery\PackageManifestCompiler}.
 *
 * Definition classes carrying this attribute are picked up at
 * `optimize:manifest` time and exposed through
 * {@see \Waaseyaa\AI\Agent\AgentDefinitionRegistry}.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsAgentDefinition
{
    /**
     * @param string $id Unique identifier (`intake.classifier`).
     * @param string $label Human label.
     * @param string $description One-line summary.
     * @param string $prompt Default prompt.
     * @param string $system Default system prompt.
     * @param string[] $tools Allow-list of tool names.
     * @param string $model Provider/model identifier (empty for default).
     * @param int $maxIterations Iteration budget.
     * @param HitlMode|null $destructiveDefault Per-agent HITL override.
     * @param string|null $requiresCapability Capability required on initiator.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly string $prompt,
        public readonly string $system = '',
        public readonly array $tools = [],
        public readonly string $model = '',
        public readonly int $maxIterations = 10,
        public readonly ?HitlMode $destructiveDefault = null,
        public readonly ?string $requiresCapability = null,
    ) {}
}

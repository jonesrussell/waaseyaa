<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent;

use Waaseyaa\AI\Agent\Enum\HitlMode;

/**
 * Bundle value object describing a named agent.
 *
 * Authoritative shape: `kitty-specs/agent-executor-01KRWPK7/data-model.md`
 * § "Value objects > AgentDefinition". Instances are produced by
 * {@see AgentDefinitionRegistry} from classes carrying
 * {@see \Waaseyaa\AI\Agent\Attribute\AsAgentDefinition}.
 *
 * @api
 */
final readonly class AgentDefinition
{
    /**
     * @param string $id Unique identifier, e.g. `intake.classifier`.
     * @param string $label Human-friendly label.
     * @param string $description One-line summary of the agent's purpose.
     * @param string $prompt Default user-facing prompt.
     * @param string $system Default system prompt.
     * @param string[] $tools Allow-list of tool names this agent may invoke.
     * @param string $model Provider/model identifier (or empty for default).
     * @param int $maxIterations Default iteration budget.
     * @param HitlMode|null $destructiveDefault Per-agent override of the HITL gate.
     * @param string|null $requiresCapability Capability required on the initiator.
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $description,
        public string $prompt,
        public string $system = '',
        public array $tools = [],
        public string $model = '',
        public int $maxIterations = 10,
        public ?HitlMode $destructiveDefault = null,
        public ?string $requiresCapability = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Aurora\AI\Agent;

/**
 * Value object describing a single action taken by an agent.
 *
 * Actions represent operations like create, update, delete, or tool_call
 * that an agent has performed (or proposes during dry-run).
 */
final readonly class AgentAction
{
    /**
     * @param string $type Action type (e.g. 'create', 'update', 'delete', 'tool_call')
     * @param string $description Human-readable description
     * @param array<string, mixed> $data Structured action data
     */
    public function __construct(
        public string $type,
        public string $description,
        public array $data = [],
    ) {}
}

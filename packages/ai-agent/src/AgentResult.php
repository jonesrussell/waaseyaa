<?php

declare(strict_types=1);

namespace Aurora\AI\Agent;

/**
 * Value object for agent execution results.
 *
 * Contains whether the execution succeeded, a human-readable message,
 * structured result data, and the list of actions taken (or proposed
 * in dry-run mode).
 */
final readonly class AgentResult
{
    /**
     * @param bool $success Whether the agent succeeded
     * @param string $message Human-readable result message
     * @param array<string, mixed> $data Structured result data
     * @param AgentAction[] $actions Actions taken (or proposed in dry run)
     */
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
        public array $actions = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param array<string, mixed> $data Structured result data
     * @param AgentAction[] $actions Actions taken
     */
    public static function success(string $message, array $data = [], array $actions = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            actions: $actions,
        );
    }

    /**
     * Create a failure result.
     *
     * @param array<string, mixed> $data Structured result data
     */
    public static function failure(string $message, array $data = []): self
    {
        return new self(
            success: false,
            message: $message,
            data: $data,
        );
    }
}

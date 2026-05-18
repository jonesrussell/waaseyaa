<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent;

/**
 * Value object for agent execution results.
 *
 * Contains whether the execution succeeded, a human-readable message,
 * structured result data, the list of actions taken (or proposed in
 * dry-run mode), and provider telemetry (token usage + USD-cent cost).
 *
 * @api
 */
final readonly class AgentResult
{
    /**
     * @param bool $success Whether the agent succeeded
     * @param string $message Human-readable result message
     * @param array<string, mixed> $data Structured result data
     * @param AgentAction[] $actions Actions taken (or proposed in dry run)
     * @param int $tokenUsageIn Provider input-token count for the run (summed across iterations).
     * @param int $tokenUsageOut Provider output-token count for the run (summed across iterations).
     * @param int|null $costCents Estimated cost in USD cents, or `null` when unknown.
     */
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
        public array $actions = [],
        public int $tokenUsageIn = 0,
        public int $tokenUsageOut = 0,
        public ?int $costCents = null,
    ) {}

    /**
     * Create a successful result.
     *
     * @param array<string, mixed> $data Structured result data
     * @param AgentAction[] $actions Actions taken
     */
    public static function success(
        string $message,
        array $data = [],
        array $actions = [],
        int $tokenUsageIn = 0,
        int $tokenUsageOut = 0,
        ?int $costCents = null,
    ): self {
        return new self(
            success: true,
            message: $message,
            data: $data,
            actions: $actions,
            tokenUsageIn: $tokenUsageIn,
            tokenUsageOut: $tokenUsageOut,
            costCents: $costCents,
        );
    }

    /**
     * Create a failure result.
     *
     * @param array<string, mixed> $data Structured result data
     */
    public static function failure(
        string $message,
        array $data = [],
        int $tokenUsageIn = 0,
        int $tokenUsageOut = 0,
        ?int $costCents = null,
    ): self {
        return new self(
            success: false,
            message: $message,
            data: $data,
            tokenUsageIn: $tokenUsageIn,
            tokenUsageOut: $tokenUsageOut,
            costCents: $costCents,
        );
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Service;

use Waaseyaa\AI\Agent\Enum\HitlMode;

/**
 * Request DTO for {@see AgentRunService::enqueue()} / {@see AgentRunService::runInline()}.
 *
 * Exactly one of `agentDefinitionId` or `bundle` must be supplied:
 *
 *  - `agentDefinitionId` — preferred for catalogued agents; the snapshot
 *    is resolved at execution time via {@see \Waaseyaa\AI\Agent\AgentDefinitionRegistry}.
 *  - `bundle` — an inline bundle blob persisted on the row so the run
 *    remains deterministic even if the definition is later edited or
 *    removed (C-009).
 *
 * `destructiveApproval` defaults to {@see HitlMode::None}, the
 * conservative "deny destructive tools" stance. Pass
 * {@see HitlMode::Interactive} only for queued runs that have a human
 * who can answer the approval prompt; inline runs reject it because no
 * human is present in the calling process.
 *
 * @api
 */
final readonly class AgentRunDraft
{
    /**
     * @param int|string $accountId Initiator account id (matches {@see \Waaseyaa\Access\AccountInterface::id()}).
     * @param string|null $agentDefinitionId Catalogued agent definition id; null when an inline bundle is supplied.
     * @param array<string, mixed>|null $bundle Inline bundle snapshot; null when a definition id is supplied.
     * @param string $prompt The user-facing prompt that drives the run.
     * @param HitlMode $destructiveApproval HITL mode for destructive tool calls.
     */
    public function __construct(
        public int|string $accountId,
        public ?string $agentDefinitionId,
        public ?array $bundle,
        public string $prompt,
        public HitlMode $destructiveApproval = HitlMode::None,
    ) {}
}

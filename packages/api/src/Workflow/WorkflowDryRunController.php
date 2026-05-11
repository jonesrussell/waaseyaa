<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Workflow;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Workflows\EditorialTransitionAccessResolver;
use Waaseyaa\Workflows\EditorialWorkflowPreset;
use Waaseyaa\Workflows\Workflow;

/**
 * Sibling controller to WorkflowDefinitionsController for read-only dry-run
 * transition checks. Kept separate to preserve single-responsibility: the
 * definitions controller is query-only; this one adds account resolution and
 * access evaluation without polluting the listing surface.
 *
 * @api
 */
final class WorkflowDryRunController
{
    /**
     * @var \Closure(): list<Workflow>
     */
    private \Closure $workflowsProvider;

    /**
     * Resolves an account by UID. Returns null when the account is not found.
     *
     * @var \Closure(int): ?AccountInterface
     */
    private \Closure $accountResolver;

    /**
     * @param (\Closure(): list<Workflow>)|null           $workflowsProvider
     * @param (\Closure(int): ?AccountInterface)|null     $accountResolver
     */
    public function __construct(
        ?\Closure $workflowsProvider = null,
        ?\Closure $accountResolver = null,
    ) {
        $this->workflowsProvider = $workflowsProvider
            ?? static fn(): array => [EditorialWorkflowPreset::create()];

        // Default resolver: always returns null (no storage in the API layer).
        // Production kernels inject a resolver backed by EntityRepository.
        $this->accountResolver = $accountResolver
            ?? static fn(int $uid): ?AccountInterface => null;
    }

    /**
     * POST /api/workflow-definitions/dry-run
     *
     * Evaluates whether the given account is allowed to perform a workflow
     * state transition without mutating any entity.
     *
     * @param array<string, mixed> $payload
     * @return array{data: array{
     *   allowed: bool,
     *   neutral: bool,
     *   forbidden: bool,
     *   reason: string|null,
     *   required_permission: string,
     *   transition_id: string,
     *   transition_label: string,
     * }}|array{errors: list<array{status: string, title: string, detail: string}>, status: int}
     */
    public function dryRun(array $payload): array
    {
        // --- Validate required fields ---
        $missing = array_values(array_filter(
            ['workflow_id', 'bundle', 'from_state', 'to_state', 'account_uid'],
            static fn(string $key): bool => !isset($payload[$key]) || $payload[$key] === '',
        ));

        if ($missing !== []) {
            return $this->validationError(sprintf(
                'Missing or empty required fields: %s.',
                implode(', ', $missing),
            ));
        }

        if (!is_int($payload['account_uid']) && !ctype_digit((string) $payload['account_uid'])) {
            return $this->validationError('account_uid must be an integer.');
        }

        $workflowId  = (string) $payload['workflow_id'];
        $bundle      = (string) $payload['bundle'];
        $fromState   = (string) $payload['from_state'];
        $toState     = (string) $payload['to_state'];
        $accountUid  = (int) $payload['account_uid'];

        // --- Resolve workflow ---
        $workflow = array_find(
            ($this->workflowsProvider)(),
            static fn(Workflow $w): bool => $w->id() === $workflowId,
        );

        if ($workflow === null) {
            return $this->notFoundError(sprintf('Workflow "%s" not found.', $workflowId));
        }

        // --- Resolve account ---
        $account = ($this->accountResolver)($accountUid);

        if ($account === null) {
            return $this->notFoundError(sprintf('Account %d not found.', $accountUid));
        }

        // --- Evaluate transition access (read-only; no entity is mutated) ---
        $resolver  = new EditorialTransitionAccessResolver($workflow);
        $result    = $resolver->canTransition($bundle, $fromState, $toState, $account);

        // Resolve transition metadata for the response shape.
        [$transitionId, $transitionLabel, $requiredPermission] =
            $this->resolveTransitionMeta($resolver, $bundle, $fromState, $toState);

        return [
            'data' => [
                'allowed'             => $result->isAllowed(),
                'neutral'             => $result->isNeutral(),
                'forbidden'           => $result->isForbidden(),
                'reason'              => $result->reason !== '' ? $result->reason : null,
                'required_permission' => $requiredPermission,
                'transition_id'       => $transitionId,
                'transition_label'    => $transitionLabel,
            ],
        ];
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * @return array{errors: list<array{status: string, title: string, detail: string}>, status: int}
     */
    private function validationError(string $detail): array
    {
        return [
            'status' => 422,
            'errors' => [['status' => '422', 'title' => 'Unprocessable Entity', 'detail' => $detail]],
        ];
    }

    /**
     * @return array{errors: list<array{status: string, title: string, detail: string}>, status: int}
     */
    private function notFoundError(string $detail): array
    {
        return [
            'status' => 404,
            'errors' => [['status' => '404', 'title' => 'Not Found', 'detail' => $detail]],
        ];
    }

    /**
     * Returns [transition_id, transition_label, required_permission] for the
     * given from/to state pair. Falls back to empty strings when the
     * transition cannot be found (e.g. the pair is invalid and canTransition
     * already returned Forbidden).
     *
     * @return array{string, string, string}
     */
    private function resolveTransitionMeta(
        EditorialTransitionAccessResolver $resolver,
        string $bundle,
        string $fromState,
        string $toState,
    ): array {
        try {
            $transition = $resolver->transition($fromState, $toState);

            return [
                $transition['id'],
                $transition['label'],
                $resolver->requiredPermission($bundle, $fromState, $toState),
            ];
        } catch (\InvalidArgumentException | \RuntimeException) {
            return ['', '', ''];
        }
    }
}

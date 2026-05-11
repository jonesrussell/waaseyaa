<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Workflow;

use Waaseyaa\Workflows\EditorialWorkflowPreset;
use Waaseyaa\Workflows\Workflow;

/**
 * API controller for workflow definitions (read-only).
 *
 * JSON payloads use camelCase keys aligned with the admin SPA TypeScript
 * types ({@see useWorkflowDefinitions}). M4A-1 covers the list endpoint only;
 * detail page, transition history, dry-run, and guard editing land in
 * follow-up sub-missions (M4A-2..M4A-5).
 *
 * @api
 */
final class WorkflowDefinitionsController
{
    /**
     * Optional factory override used by tests. Defaults to the editorial preset
     * factory; when multiple workflows become pluggable, replace with a
     * registry-backed iterator.
     *
     * @var \Closure(): list<Workflow>
     */
    private \Closure $workflowsProvider;

    /**
     * @param (\Closure(): list<Workflow>)|null $workflowsProvider
     */
    public function __construct(?\Closure $workflowsProvider = null)
    {
        $this->workflowsProvider = $workflowsProvider
            ?? static fn(): array => [EditorialWorkflowPreset::create()];
    }

    /**
     * GET /api/workflow-definitions
     *
     * @return array{data: list<array{
     *   id: string,
     *   label: string,
     *   states: list<array{id: string, label: string, weight: int, metadata: array<string, mixed>}>,
     *   transitions: list<array{id: string, label: string, from: list<string>, to: string, weight: int}>
     * }>}
     */
    public function list(): array
    {
        $workflows = ($this->workflowsProvider)();

        return [
            'data' => array_map(self::serializeWorkflow(...), $workflows),
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   label: string,
     *   states: list<array{id: string, label: string, weight: int, metadata: array<string, mixed>}>,
     *   transitions: list<array{id: string, label: string, from: list<string>, to: string, weight: int}>
     * }
     */
    private static function serializeWorkflow(Workflow $workflow): array
    {
        $states = [];
        foreach ($workflow->getStates() as $state) {
            $states[] = [
                'id' => $state->id,
                'label' => $state->label,
                'weight' => $state->weight,
                'metadata' => $state->metadata,
            ];
        }

        $transitions = [];
        foreach ($workflow->getTransitions() as $transition) {
            $transitions[] = [
                'id' => $transition->id,
                'label' => $transition->label,
                'from' => array_values($transition->from),
                'to' => $transition->to,
                'weight' => $transition->weight,
            ];
        }

        return [
            'id' => $workflow->id(),
            'label' => $workflow->label(),
            'states' => $states,
            'transitions' => $transitions,
        ];
    }
}

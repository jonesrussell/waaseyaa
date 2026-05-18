<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Service;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Waaseyaa\AI\Agent\Entity\AgentRun;
use Waaseyaa\AI\Agent\Enum\HitlMode;
use Waaseyaa\AI\Agent\Enum\RunStatus;
use Waaseyaa\AI\Agent\Message\RunAgent;
use Waaseyaa\AI\Agent\Message\RunAgentHandler;
use Waaseyaa\AI\Agent\Repository\AgentRunRepository;

/**
 * Application-facing facade for kicking off an {@see AgentRun}.
 *
 * Two entry points, both producing the same persistence state and the
 * same audit-row sequence (FR-008):
 *
 *  - {@see enqueue()} — production async path. Persists the row at
 *    `queued` and dispatches a {@see RunAgent} message onto the
 *    Messenger bus. A worker picks the message up and routes through
 *    {@see RunAgentHandler::__invoke()}.
 *  - {@see runInline()} — dev / CLI / smoke-test sync path. Persists
 *    the row at `queued` and invokes {@see RunAgentHandler::__invoke()}
 *    in-process. Identical pipeline, no transport hop. Inline mode
 *    refuses {@see HitlMode::Interactive} because no human is present to
 *    answer the approval prompt.
 *
 * The service stamps `id`, `queued_at`, and `status='queued'` so the
 * row is canonical regardless of which method the caller chose.
 *
 * @api
 */
final class AgentRunService
{
    /** @var \Closure(): \DateTimeImmutable */
    private \Closure $now;

    /** @var \Closure(): string */
    private \Closure $idFactory;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly AgentRunRepository $runRepository,
        private readonly RunAgentHandler $inlineHandler,
        ?\Closure $now = null,
        ?\Closure $idFactory = null,
    ) {
        $this->now = $now ?? static fn(): \DateTimeImmutable => new \DateTimeImmutable('now');
        $this->idFactory = $idFactory ?? static fn(): string => Uuid::v4()->toRfc4122();
    }

    /**
     * Persist a queued run and dispatch a {@see RunAgent} message.
     */
    public function enqueue(AgentRunDraft $draft): AgentRun
    {
        $run = $this->persistDraft($draft);

        $this->messageBus->dispatch(new RunAgent(Uuid::fromString((string) $run->get('id'))));

        $reloaded = $this->runRepository->find((string) $run->get('id'));

        return $reloaded ?? $run;
    }

    /**
     * Persist a queued run and run {@see RunAgentHandler::__invoke()} in-process.
     *
     * Returns the post-execution row so callers can inspect the
     * terminal status without a second `find()`.
     *
     * @throws \InvalidArgumentException If the draft requests interactive HITL.
     */
    public function runInline(AgentRunDraft $draft): AgentRun
    {
        if ($draft->destructiveApproval === HitlMode::Interactive) {
            throw new \InvalidArgumentException(
                'AgentRunService::runInline(): HitlMode::Interactive is not supported '
                . 'because no human is available to respond to approval prompts. '
                . 'Use enqueue() for interactive runs or pick HitlMode::None / HitlMode::All.',
            );
        }

        $run = $this->persistDraft($draft);

        ($this->inlineHandler)(new RunAgent(Uuid::fromString((string) $run->get('id'))));

        $reloaded = $this->runRepository->find((string) $run->get('id'));

        return $reloaded ?? $run;
    }

    private function persistDraft(AgentRunDraft $draft): AgentRun
    {
        $this->validateDraft($draft);

        $id = ($this->idFactory)();
        $queuedAt = ($this->now)();

        $bundleJson = '{}';
        if ($draft->bundle !== null) {
            try {
                $bundleJson = json_encode($draft->bundle, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException(
                    'AgentRunService: failed to encode bundle as JSON: ' . $e->getMessage(),
                    previous: $e,
                );
            }
        }

        $run = new AgentRun([
            'id' => $id,
            'account_id' => $draft->accountId,
            'agent_definition_id' => $draft->agentDefinitionId,
            'bundle_json' => $bundleJson,
            'status' => RunStatus::Queued->value,
            'destructive_approval' => $draft->destructiveApproval->value,
            'pending_approval_call_id' => null,
            'prompt' => $draft->prompt,
            'response' => null,
            'transcript_json' => '[]',
            'token_usage_in' => 0,
            'token_usage_out' => 0,
            'cost_cents' => null,
            'tool_call_count' => 0,
            'queued_at' => $queuedAt->format('Y-m-d H:i:s.uP'),
            'started_at' => null,
            'finished_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);
        $run->enforceIsNew(true);

        $this->runRepository->save($run);

        return $run;
    }

    private function validateDraft(AgentRunDraft $draft): void
    {
        if (trim($draft->prompt) === '') {
            throw new \InvalidArgumentException('AgentRunService: draft.prompt must not be empty.');
        }

        if ($draft->agentDefinitionId === null && $draft->bundle === null) {
            throw new \InvalidArgumentException(
                'AgentRunService: draft must supply either agentDefinitionId or bundle (both null).',
            );
        }

        if ($draft->agentDefinitionId !== null && $draft->bundle !== null) {
            throw new \InvalidArgumentException(
                'AgentRunService: draft must supply exactly one of agentDefinitionId or bundle (both supplied).',
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Ai;

use Waaseyaa\AI\Agent\AgentDefinitionRegistry;
use Waaseyaa\AI\Agent\Entity\AgentRun;
use Waaseyaa\AI\Agent\Enum\HitlMode;
use Waaseyaa\AI\Agent\Service\AgentRunDraft;
use Waaseyaa\AI\Agent\Service\AgentRunService;
use Waaseyaa\CLI\CliIO;

/**
 * `bin/waaseyaa ai:run <prompt> [...]` — the operator's primary entry to
 * the agent runtime (FR-005).
 *
 * Two execution modes:
 *  - **async (default):** persists a queued {@see AgentRun} via
 *    {@see AgentRunService::enqueue()}, dispatches a {@see \Waaseyaa\AI\Agent\Message\RunAgent}
 *    onto the Messenger bus, and prints the new `run_id` so a worker
 *    (or `bin/waaseyaa queue:work`) can pick it up.
 *  - **inline (`--inline`):** persists a queued {@see AgentRun} and runs
 *    the worker handler in-process via {@see AgentRunService::runInline()}.
 *    Used for smoke tests and dev iteration; the wall-clock target is
 *    < 10 s on `NullLlmProvider` (NFR-001).
 *
 * Edge case: `--inline` + `--destructive-approval=interactive` is
 * rejected at parse time — no human is present in the calling process
 * to answer the approval prompt.
 *
 * @api
 */
final class AiRunCommand
{
    public const HITL_NONE = 'none';
    public const HITL_ALL = 'all';
    public const HITL_INTERACTIVE = 'interactive';

    public function __construct(
        private readonly AgentRunService $runService,
        private readonly AgentDefinitionRegistry $definitionRegistry,
        /** @var array<string, mixed> */
        private readonly array $aiConfig = [],
        private readonly int|string $serviceAccountId = 0,
    ) {}

    public function execute(CliIO $io): int
    {
        $prompt = $io->argument('prompt');
        if (!\is_string($prompt) || trim($prompt) === '') {
            $io->error('ai:run: <prompt> argument is required.');
            return 1;
        }

        $inline = (bool) $io->option('inline');
        $hitlRaw = $io->option('destructive-approval');
        $hitl = \is_string($hitlRaw) && $hitlRaw !== '' ? $hitlRaw : self::HITL_NONE;

        if (!\in_array($hitl, [self::HITL_NONE, self::HITL_ALL, self::HITL_INTERACTIVE], true)) {
            $io->error(\sprintf(
                'ai:run: --destructive-approval must be one of "none", "all", or "interactive"; got "%s".',
                $hitl,
            ));
            return 1;
        }

        // Spec edge case: --inline + interactive HITL is impossible (no human present).
        if ($inline && $hitl === self::HITL_INTERACTIVE) {
            $io->error(
                'ai:run: --inline cannot be combined with --destructive-approval=interactive. '
                . 'No human is present in the calling process to answer approval prompts. '
                . 'Use --destructive-approval=none or =all for inline runs, or drop --inline.',
            );
            return 1;
        }

        $hitlMode = match ($hitl) {
            self::HITL_NONE => HitlMode::None,
            self::HITL_ALL => HitlMode::All,
            self::HITL_INTERACTIVE => HitlMode::Interactive,
        };

        $agentId = $io->option('agent');
        $agentId = \is_string($agentId) && $agentId !== '' ? $agentId : null;

        $accountOption = $io->option('account');
        $accountId = $this->resolveAccountId($accountOption);

        $dryRun = (bool) $io->option('dry-run');
        $watch = (bool) $io->option('watch');

        $bundle = null;
        if ($agentId !== null) {
            if (!$this->definitionRegistry->has($agentId)) {
                $io->error(\sprintf('ai:run: agent definition "%s" is not registered.', $agentId));
                return 1;
            }
        } else {
            // No explicit agent: build an ad-hoc bundle from config.ai.providers[0]'s default model.
            $bundle = $this->buildDefaultBundle($dryRun);
            if ($bundle === null) {
                $io->error(
                    'ai:run: no --agent supplied and config.ai.providers[0] is missing or has no '
                    . 'model_default. Supply --agent=<id> or configure at least one provider.',
                );
                return 1;
            }
        }

        if ($dryRun && $bundle !== null) {
            $bundle['dry_run'] = true;
        }

        $draft = new AgentRunDraft(
            accountId: $accountId,
            agentDefinitionId: $agentId,
            bundle: $bundle,
            prompt: $prompt,
            destructiveApproval: $hitlMode,
        );

        if ($inline) {
            return $this->runInline($io, $draft);
        }

        return $this->runAsync($io, $draft, $watch);
    }

    private function runInline(CliIO $io, AgentRunDraft $draft): int
    {
        try {
            $run = $this->runService->runInline($draft);
        } catch (\Throwable $e) {
            $io->error(\sprintf('ai:run --inline: %s', $e->getMessage()));
            return 1;
        }

        $this->printRunSummary($io, $run);

        return $run->isTerminal() && $run->getStatus()->value === 'failed' ? 1 : 0;
    }

    private function runAsync(CliIO $io, AgentRunDraft $draft, bool $watch): int
    {
        try {
            $run = $this->runService->enqueue($draft);
        } catch (\Throwable $e) {
            $io->error(\sprintf('ai:run: %s', $e->getMessage()));
            return 1;
        }

        $runId = (string) $run->get('id');
        $io->writeln(\sprintf('run_id: %s', $runId));
        $io->writeln(\sprintf('status: %s', (string) $run->get('status')));

        if ($watch) {
            $io->writeln(\sprintf(
                'watch: SSE consumer would attach to /broadcast?channels=agent.run.%s (--watch is informational here).',
                $runId,
            ));
        }

        return 0;
    }

    private function printRunSummary(CliIO $io, AgentRun $run): void
    {
        $runId = (string) $run->get('id');
        $status = (string) $run->get('status');
        $response = $run->get('response');

        $io->writeln(\sprintf('run_id: %s', $runId));
        $io->writeln(\sprintf('status: %s', $status));
        if (\is_string($response) && $response !== '') {
            $io->writeln('response:');
            $io->writeln($response);
        }
    }

    /**
     * Build an ad-hoc bundle pointing at `config.ai.providers[0]`'s default
     * model, returning null if no provider is configured.
     *
     * @return array<string, mixed>|null
     */
    private function buildDefaultBundle(bool $dryRun): ?array
    {
        $providers = $this->aiConfig['providers'] ?? null;
        if (!\is_array($providers) || $providers === []) {
            return null;
        }

        $first = $providers[0] ?? null;
        if (!\is_array($first)) {
            return null;
        }

        $modelDefault = $first['model_default'] ?? null;
        if (!\is_string($modelDefault) || $modelDefault === '') {
            return null;
        }

        $providerId = $first['id'] ?? null;

        $bundle = [
            'model' => $modelDefault,
            'tools' => [],
        ];
        if (\is_string($providerId) && $providerId !== '') {
            $bundle['provider'] = $providerId;
        }
        if ($dryRun) {
            $bundle['dry_run'] = true;
        }

        return $bundle;
    }

    private function resolveAccountId(string|int|float|bool|array|null $option): int|string
    {
        if (\is_int($option)) {
            return $option;
        }
        if (\is_string($option) && $option !== '') {
            if (ctype_digit($option)) {
                return (int) $option;
            }
            return $option;
        }
        return $this->serviceAccountId;
    }
}

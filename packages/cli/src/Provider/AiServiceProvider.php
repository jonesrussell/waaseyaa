<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\AI\Agent\AgentDefinitionRegistry;
use Waaseyaa\AI\Agent\Reaper\StalledRunReaper;
use Waaseyaa\AI\Agent\Repository\AgentAuditLogRepository;
use Waaseyaa\AI\Agent\Repository\AgentRunRepository;
use Waaseyaa\AI\Agent\Service\AgentRunService;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\Command\Ai\AiPurgeRunsCommand;
use Waaseyaa\CLI\Command\Ai\AiReapStalledRunsCommand;
use Waaseyaa\CLI\Command\Ai\AiRunCommand;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\HttpClient\PhpStreamSseClient;

/**
 * Registers the operator-facing `ai:*` CLI commands:
 *
 *  - `ai:run` (FR-005) — kick off an agent run (inline or async).
 *  - `ai:purge-runs` (FR-006) — retention sweep.
 *  - `ai:reap-stalled-runs` (FR-007, NFR-004) — crash recovery.
 *
 * @api
 */
final class AiServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void
    {
        $this->singleton(
            AiRunCommand::class,
            function (): AiRunCommand {
                $configBaseUrl = $this->config['app']['base_url'] ?? null;
                $envBaseUrl = $_ENV['WAASEYAA_BASE_URL'] ?? null;
                $getenvBaseUrl = getenv('WAASEYAA_BASE_URL');
                if (\is_string($configBaseUrl) && $configBaseUrl !== '') {
                    $baseUrl = $configBaseUrl;
                } elseif (\is_string($envBaseUrl) && $envBaseUrl !== '') {
                    $baseUrl = $envBaseUrl;
                } elseif (\is_string($getenvBaseUrl) && $getenvBaseUrl !== '') {
                    $baseUrl = $getenvBaseUrl;
                } else {
                    $baseUrl = '';
                }
                return new AiRunCommand(
                    runService: $this->resolve(AgentRunService::class),
                    definitionRegistry: $this->resolve(AgentDefinitionRegistry::class),
                    aiConfig: \is_array($this->config['ai'] ?? null) ? $this->config['ai'] : [],
                    serviceAccountId: $this->config['ai']['service_account_id'] ?? 0,
                    sseClient: new PhpStreamSseClient(),
                    baseUrl: $baseUrl,
                );
            },
        );

        $this->singleton(
            AiPurgeRunsCommand::class,
            fn(): AiPurgeRunsCommand => new AiPurgeRunsCommand(
                runRepository: $this->resolve(AgentRunRepository::class),
                auditRepository: $this->resolve(AgentAuditLogRepository::class),
                runEntityRepository: $this->resolve(EntityRepositoryInterface::class),
                defaultRetentionDays: (int) ($this->config['ai']['run_retention_days'] ?? 30),
            ),
        );

        $this->singleton(
            AiReapStalledRunsCommand::class,
            fn(): AiReapStalledRunsCommand => new AiReapStalledRunsCommand(
                reaper: $this->resolve(StalledRunReaper::class),
                defaultMaxRuntimeSeconds: (int) ($this->config['ai']['max_runtime_seconds'] ?? 600),
            ),
        );
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'ai:run',
            description: 'Run an AI agent (FR-005). Use --inline for sync execution, otherwise async via the Messenger bus.',
            arguments: [
                new ArgumentDefinition(
                    name: 'prompt',
                    mode: ArgumentMode::Required,
                    description: 'The user-facing prompt that drives the run.',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'inline',
                    mode: OptionMode::None,
                    description: 'Run synchronously via AgentRunService::runInline() instead of enqueueing.',
                ),
                new OptionDefinition(
                    name: 'agent',
                    mode: OptionMode::Required,
                    description: 'Resolve a named AgentDefinition. Defaults to an ad-hoc bundle from config.ai.providers[0].',
                    default: '',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    mode: OptionMode::None,
                    description: 'Call each tool\'s dryRun() instead of execute().',
                ),
                new OptionDefinition(
                    name: 'watch',
                    mode: OptionMode::None,
                    description: 'For async runs, attach an SSE consumer to /broadcast?channels=agent.run.<id>.',
                ),
                new OptionDefinition(
                    name: 'destructive-approval',
                    mode: OptionMode::Required,
                    description: 'HITL mode for destructive tools: none|all|interactive (default: none).',
                    default: 'none',
                ),
                new OptionDefinition(
                    name: 'account',
                    mode: OptionMode::Required,
                    description: 'Initiator account id; defaults to the service account.',
                    default: '',
                ),
            ],
            handler: [AiRunCommand::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'ai:purge-runs',
            description: 'Purge AgentRun rows and their AgentAuditLog entries past the retention window (FR-006).',
            options: [
                new OptionDefinition(
                    name: 'retention-days',
                    mode: OptionMode::Required,
                    description: 'Retention window in days. Defaults to config.ai.run_retention_days (30).',
                    default: '',
                ),
            ],
            handler: [AiPurgeRunsCommand::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'ai:reap-stalled-runs',
            description: 'Flip stalled `running` agent runs to terminal failed/worker_crashed (FR-007, NFR-004).',
            options: [
                new OptionDefinition(
                    name: 'max-runtime-seconds',
                    mode: OptionMode::Required,
                    description: 'Stall threshold in seconds. Defaults to config.ai.max_runtime_seconds (600).',
                    default: '',
                ),
            ],
            handler: [AiReapStalledRunsCommand::class, 'execute'],
        );
    }
}

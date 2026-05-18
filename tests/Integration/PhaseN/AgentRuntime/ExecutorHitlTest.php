<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\PhaseN\AgentRuntime;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\AI\Agent\AgentExecutor;
use Waaseyaa\AI\Agent\Entity\AgentRun;
use Waaseyaa\AI\Agent\Enum\HitlMode;
use Waaseyaa\AI\Agent\Enum\RunStatus;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\MessageResponse;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\AI\Agent\Provider\RateLimitException;
use Waaseyaa\AI\Agent\Repository\AgentAuditLogRepository;
use Waaseyaa\AI\Agent\Repository\AgentRunRepository;
use Waaseyaa\AI\Tools\AgentTool;
use Waaseyaa\AI\Tools\AgentToolInterface;
use Waaseyaa\AI\Tools\AgentToolResult;
use Waaseyaa\AI\Tools\ToolNotFoundException;
use Waaseyaa\AI\Tools\ToolRegistryInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Integration test covering the WP03 executor surface:
 *
 *  - HITL gates (`None` denies, `All` synth-grants).
 *  - Cancellation polling honours `Cancelling` within bounded iterations.
 *  - Provider retry exhaustion terminates with `provider_rate_limited`.
 *  - `AgentResult` carries token usage telemetry.
 *
 * The test harness wires the canonical entity-storage pipeline against an
 * in-memory SQLite, builds an in-memory {@see ToolRegistryInterface},
 * and drives the executor with deterministic provider + sleep stubs.
 *
 * @api
 */
#[CoversNothing]
final class ExecutorHitlTest extends TestCase
{
    private DBALDatabase $database;
    private AgentRunRepository $runRepository;
    private AgentAuditLogRepository $auditRepository;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite();
        $this->migrateSchema();

        $dispatcher = new EventDispatcher();
        $resolver = new SingleConnectionResolver($this->database);
        $driver = new SqlStorageDriver($resolver);

        $runType = new EntityType(
            id: 'agent_run',
            label: 'Agent run',
            class: AgentRun::class,
            keys: ['id' => 'id', 'uuid' => 'id', 'label' => 'id'],
        );
        $logType = new EntityType(
            id: 'agent_audit_log',
            label: 'Agent audit log',
            class: \Waaseyaa\AI\Agent\Entity\AgentAuditLog::class,
            keys: ['id' => 'id', 'uuid' => 'id', 'label' => 'event_type'],
        );

        $runEntityRepo = new EntityRepository($runType, $driver, $dispatcher);
        $logEntityRepo = new EntityRepository($logType, $driver, $dispatcher);

        $this->runRepository = new AgentRunRepository($runEntityRepo, $this->database);
        $this->auditRepository = new AgentAuditLogRepository($logEntityRepo, $this->database);
    }

    #[Test]
    public function hitlNoneDeniesDestructiveTool(): void
    {
        $run = $this->seedRun(HitlMode::None);
        $tool = $this->makeDestructiveTool();
        $registry = $this->registryWith([$tool]);

        $provider = $this->providerEmittingToolUse($tool->name);

        $executor = $this->makeExecutor($registry);
        $result = $executor->executeRun(
            $run,
            $this->fakeAccount(99),
            $provider,
            messages: [['role' => 'user', 'content' => 'go']],
            maxIterations: 3,
        );

        self::assertFalse($result->success);
        self::assertSame('destructive_denied', $result->data['error_code']);
        $fresh = $this->runRepository->find((string) $run->get('id'));
        self::assertSame(RunStatus::Failed, $fresh?->getStatus());
    }

    #[Test]
    public function hitlAllSynthGrantsAndProceeds(): void
    {
        $run = $this->seedRun(HitlMode::All);
        $tool = $this->makeDestructiveTool();
        $registry = $this->registryWith([$tool]);

        $provider = $this->providerToolUseThenEnd($tool->name);

        $executor = $this->makeExecutor($registry);
        $result = $executor->executeRun(
            $run,
            $this->fakeAccount(99),
            $provider,
            messages: [['role' => 'user', 'content' => 'go']],
            maxIterations: 3,
        );

        self::assertTrue($result->success, sprintf('result message=%s', $result->message));
        self::assertGreaterThan(0, $result->tokenUsageOut);
    }

    #[Test]
    public function cancellationHonouredWithinBoundedIterations(): void
    {
        $run = $this->seedRun(HitlMode::None);
        $registry = $this->registryWith([]);

        // Provider that always asks for a tool, infinite-loop fodder.
        $provider = new class implements ProviderInterface {
            public function sendMessage(MessageRequest $request): MessageResponse
            {
                return new MessageResponse(
                    content: [['type' => 'text', 'text' => 'thinking...']],
                    stopReason: 'end_turn',
                    usage: ['input_tokens' => 1, 'output_tokens' => 1],
                );
            }
        };

        $database = $this->database;
        $runId = (string) $run->get('id');
        // Pre-mark Cancelling so the very first iteration entry detects it.
        $database->update('agent_run')
            ->fields(['status' => RunStatus::Cancelling->value])
            ->condition('id', $runId)
            ->execute();

        $executor = $this->makeExecutor($registry);
        $result = $executor->executeRun(
            $run,
            $this->fakeAccount(99),
            $provider,
            messages: [['role' => 'user', 'content' => 'go']],
            maxIterations: 5,
        );

        self::assertFalse($result->success);
        self::assertTrue($result->data['cancelled'] ?? false);
        self::assertSame(RunStatus::Cancelled, $this->runRepository->find($runId)?->getStatus());
    }

    #[Test]
    public function providerRetryExhaustionTerminatesWithRateLimited(): void
    {
        $run = $this->seedRun(HitlMode::None);
        $registry = $this->registryWith([]);

        $provider = new class implements ProviderInterface {
            public function sendMessage(MessageRequest $request): MessageResponse
            {
                throw new RateLimitException(retryAfterSeconds: 1, message: 'limit');
            }
        };

        $executor = $this->makeExecutor($registry);
        $result = $executor->executeRun(
            $run,
            $this->fakeAccount(99),
            $provider,
            messages: [['role' => 'user', 'content' => 'go']],
            maxIterations: 1,
        );

        self::assertFalse($result->success);
        $fresh = $this->runRepository->find((string) $run->get('id'));
        self::assertSame('provider_rate_limited', $fresh?->get('error_code'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeExecutor(ToolRegistryInterface $registry): AgentExecutor
    {
        return new AgentExecutor(
            toolRegistry: $registry,
            runRepository: $this->runRepository,
            auditRepository: $this->auditRepository,
            transcriptMaxBytes: 65536,
            hitlPollIntervalMs: 1,
            hitlTimeoutSeconds: 1,
            sleepMs: static fn (int $ms): null => null,
        );
    }

    private function seedRun(HitlMode $approval): AgentRun
    {
        $run = new AgentRun([
            'id' => '01J' . str_pad((string) random_int(1000, 9999), 23, '0'),
            'account_id' => 99,
            'agent_definition_id' => null,
            'bundle_json' => '{}',
            'status' => RunStatus::Running->value,
            'destructive_approval' => $approval->value,
            'pending_approval_call_id' => null,
            'prompt' => 'test',
            'response' => null,
            'transcript_json' => '',
            'token_usage_in' => 0,
            'token_usage_out' => 0,
            'cost_cents' => null,
            'tool_call_count' => 0,
            'queued_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.uP'),
            'started_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.uP'),
            'finished_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);
        $run->enforceIsNew(true);
        $this->runRepository->save($run);

        return $run;
    }

    private function fakeAccount(int $id): AccountInterface
    {
        return new class ($id) implements AccountInterface {
            public function __construct(private readonly int $accountId) {}
            public function id(): int { return $this->accountId; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['administrator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }

    private function registryWith(array $tools): ToolRegistryInterface
    {
        return new class ($tools) implements ToolRegistryInterface {
            /** @var array<string, AgentTool> */
            private array $map = [];

            public function __construct(array $tools)
            {
                foreach ($tools as $tool) {
                    $this->map[$tool->name] = $tool;
                }
            }

            public function register(AgentTool $tool): void
            {
                $this->map[$tool->name] = $tool;
            }

            public function get(string $name): AgentTool
            {
                if (!isset($this->map[$name])) {
                    throw ToolNotFoundException::forName($name);
                }
                return $this->map[$name];
            }

            public function has(string $name): bool
            {
                return isset($this->map[$name]);
            }

            public function all(): iterable
            {
                return array_values($this->map);
            }
        };
    }

    private function makeDestructiveTool(): AgentTool
    {
        $impl = new class implements AgentToolInterface {
            public function execute(array $arguments, AccountInterface $account): AgentToolResult
            {
                return AgentToolResult::success([['type' => 'text', 'text' => 'done']]);
            }
            public function dryRun(array $arguments, AccountInterface $account): AgentToolResult
            {
                return AgentToolResult::error('dry_run_not_supported');
            }
            public function argumentsForAudit(array $arguments): array { return $arguments; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }
            public function description(): string { return 'Destructive test tool.'; }
        };

        return new AgentTool(
            name: 'fixture.destructive',
            capability: 'tool.fixture.destructive',
            destructive: true,
            dryRunSupported: false,
            category: 'fixture',
            inputSchema: ['type' => 'object', 'properties' => []],
            impl: $impl,
        );
    }

    private function providerEmittingToolUse(string $toolName): ProviderInterface
    {
        return new class ($toolName) implements ProviderInterface {
            public function __construct(private readonly string $toolName) {}
            public function sendMessage(MessageRequest $request): MessageResponse
            {
                return new MessageResponse(
                    content: [['type' => 'tool_use', 'id' => 'tu_x', 'name' => $this->toolName, 'input' => []]],
                    stopReason: 'tool_use',
                    usage: ['input_tokens' => 5, 'output_tokens' => 1],
                );
            }
        };
    }

    private function providerToolUseThenEnd(string $toolName): ProviderInterface
    {
        return new class ($toolName) implements ProviderInterface {
            private int $turn = 0;
            public function __construct(private readonly string $toolName) {}
            public function sendMessage(MessageRequest $request): MessageResponse
            {
                $this->turn++;
                if ($this->turn === 1) {
                    return new MessageResponse(
                        content: [['type' => 'tool_use', 'id' => 'tu_a', 'name' => $this->toolName, 'input' => []]],
                        stopReason: 'tool_use',
                        usage: ['input_tokens' => 5, 'output_tokens' => 2],
                    );
                }
                return new MessageResponse(
                    content: [['type' => 'text', 'text' => 'finished']],
                    stopReason: 'end_turn',
                    usage: ['input_tokens' => 5, 'output_tokens' => 2],
                );
            }
        };
    }

    private function migrateSchema(): void
    {
        $migrationFile = \dirname(__DIR__, 4)
            . '/packages/ai-agent/migrations/2026_05_18_000001_create_agent_run.php';
        $migration = require $migrationFile;
        \assert($migration instanceof Migration);

        $schema = new SchemaBuilder($this->database->getConnection());
        $migration->up($schema);
    }
}

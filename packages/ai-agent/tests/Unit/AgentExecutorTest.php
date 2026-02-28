<?php

declare(strict_types=1);

namespace Aurora\AI\Agent\Tests\Unit;

use Aurora\Access\AccountInterface;
use Aurora\AI\Agent\AgentContext;
use Aurora\AI\Agent\AgentExecutor;
use Aurora\AI\Agent\AgentResult;
use Aurora\AI\Schema\Mcp\McpToolExecutor;
use Aurora\Entity\EntityTypeManagerInterface;
use Aurora\Entity\Storage\EntityStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentExecutor::class)]
final class AgentExecutorTest extends TestCase
{
    private AgentExecutor $executor;
    private EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject $entityTypeManager;
    private EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject $storage;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $this->storage = $this->createMock(EntityStorageInterface::class);

        $this->entityTypeManager
            ->method('hasDefinition')
            ->willReturnCallback(fn (string $id) => $id === 'node');

        $this->entityTypeManager
            ->method('getStorage')
            ->willReturn($this->storage);

        $toolExecutor = new McpToolExecutor($this->entityTypeManager);
        $this->executor = new AgentExecutor($toolExecutor);
    }

    private function createContext(int $accountId = 1, array $parameters = []): AgentContext
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($accountId);

        return new AgentContext(
            account: $account,
            parameters: $parameters,
        );
    }

    public function testExecuteAgent(): void
    {
        $agent = new TestAgent();
        $context = $this->createContext(1, ['title' => 'My Article']);

        $result = $this->executor->execute($agent, $context);

        self::assertTrue($result->success);
        self::assertSame('Test agent executed', $result->message);
        self::assertSame(['parameters' => ['title' => 'My Article']], $result->data);
        self::assertCount(1, $result->actions);
        self::assertSame('create', $result->actions[0]->type);
    }

    public function testDryRunAgent(): void
    {
        $agent = new TestAgent();
        $context = $this->createContext(2, ['type' => 'article']);

        $result = $this->executor->dryRun($agent, $context);

        self::assertTrue($result->success);
        self::assertSame('Test agent would create entity', $result->message);
        self::assertCount(1, $result->actions);
        self::assertSame('create', $result->actions[0]->type);
        self::assertSame('Would create test entity', $result->actions[0]->description);
    }

    public function testAuditLogCapturesExecution(): void
    {
        $agent = new TestAgent();
        $context = $this->createContext(5);

        $this->executor->execute($agent, $context);

        $log = $this->executor->getAuditLog();
        self::assertCount(1, $log);
        self::assertSame(TestAgent::class, $log[0]->agentId);
        self::assertSame(5, $log[0]->accountId);
        self::assertSame('execute', $log[0]->action);
        self::assertTrue($log[0]->success);
        self::assertSame('Test agent executed', $log[0]->message);
        self::assertGreaterThan(0, $log[0]->timestamp);
    }

    public function testAuditLogCapturesDryRun(): void
    {
        $agent = new TestAgent();
        $context = $this->createContext(3);

        $this->executor->dryRun($agent, $context);

        $log = $this->executor->getAuditLog();
        self::assertCount(1, $log);
        self::assertSame('dry_run', $log[0]->action);
        self::assertTrue($log[0]->success);
    }

    public function testFailedAgentLoggedAsFailure(): void
    {
        $agent = new TestAgent();
        $agent->setExecuteResult(AgentResult::failure('Access denied'));
        $context = $this->createContext(7);

        $result = $this->executor->execute($agent, $context);

        self::assertFalse($result->success);
        self::assertSame('Access denied', $result->message);

        $log = $this->executor->getAuditLog();
        self::assertCount(1, $log);
        self::assertFalse($log[0]->success);
        self::assertSame('Access denied', $log[0]->message);
    }

    public function testExceptionHandlingWrapsAsFailure(): void
    {
        $agent = new TestAgent();
        $agent->setExecuteException(new \RuntimeException('Database connection lost'));
        $context = $this->createContext(1);

        $result = $this->executor->execute($agent, $context);

        self::assertFalse($result->success);
        self::assertStringContainsString('Database connection lost', $result->message);
        self::assertSame(\RuntimeException::class, $result->data['exception']);

        $log = $this->executor->getAuditLog();
        self::assertCount(1, $log);
        self::assertFalse($log[0]->success);
        self::assertStringContainsString('Database connection lost', $log[0]->message);
    }

    public function testDryRunExceptionHandling(): void
    {
        $agent = new TestAgent();
        $agent->setDryRunException(new \InvalidArgumentException('Invalid parameters'));
        $context = $this->createContext(1);

        $result = $this->executor->dryRun($agent, $context);

        self::assertFalse($result->success);
        self::assertStringContainsString('Invalid parameters', $result->message);
        self::assertSame(\InvalidArgumentException::class, $result->data['exception']);

        $log = $this->executor->getAuditLog();
        self::assertCount(1, $log);
        self::assertSame('dry_run', $log[0]->action);
        self::assertFalse($log[0]->success);
    }

    public function testToolExecution(): void
    {
        $entity = $this->createMock(\Aurora\Entity\EntityInterface::class);
        $entity->method('id')->willReturn('123');
        $entity->method('toArray')->willReturn(['title' => 'Test']);

        $this->storage
            ->method('create')
            ->willReturn($entity);

        $this->storage
            ->method('save')
            ->willReturn(1);

        $context = $this->createContext(10);
        $result = $this->executor->executeTool('create_node', ['attributes' => ['title' => 'Test']], $context);

        self::assertArrayHasKey('content', $result);
        self::assertArrayNotHasKey('isError', $result);

        $log = $this->executor->getAuditLog();
        self::assertCount(1, $log);
        self::assertSame('tool', $log[0]->agentId);
        self::assertSame(10, $log[0]->accountId);
        self::assertSame('tool_call', $log[0]->action);
        self::assertTrue($log[0]->success);
        self::assertSame('Tool call: create_node', $log[0]->message);
        self::assertSame('create_node', $log[0]->data['tool']);
    }

    public function testToolExecutionWithUnknownEntityType(): void
    {
        $context = $this->createContext(1);
        $result = $this->executor->executeTool('create_unknown', ['attributes' => []], $context);

        self::assertTrue($result['isError']);

        $log = $this->executor->getAuditLog();
        self::assertCount(1, $log);
        self::assertFalse($log[0]->success);
    }

    public function testToolExecutionWithInvalidTool(): void
    {
        $context = $this->createContext(1);
        // An invalid tool name triggers an exception in McpToolExecutor
        $result = $this->executor->executeTool('totally_invalid', [], $context);

        // The executor should catch the exception and return error
        self::assertArrayHasKey('content', $result);

        $log = $this->executor->getAuditLog();
        self::assertCount(1, $log);
        self::assertFalse($log[0]->success);
    }

    public function testMultipleExecutionsAccumulateAuditLog(): void
    {
        $agent = new TestAgent();
        $context = $this->createContext(1);

        $this->executor->execute($agent, $context);
        $this->executor->dryRun($agent, $context);
        $this->executor->execute($agent, $context);

        $log = $this->executor->getAuditLog();
        self::assertCount(3, $log);
        self::assertSame('execute', $log[0]->action);
        self::assertSame('dry_run', $log[1]->action);
        self::assertSame('execute', $log[2]->action);
    }

    public function testAuditLogStartsEmpty(): void
    {
        self::assertSame([], $this->executor->getAuditLog());
    }
}

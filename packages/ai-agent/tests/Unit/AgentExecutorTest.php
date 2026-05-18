<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\AgentExecutor;

/**
 * Smoke test for the WP03-rewritten {@see AgentExecutor}.
 *
 * Behavioural tests against the new executor (HITL, cancellation,
 * retry, transcript cap) live in
 * `tests/Integration/PhaseN/AgentRuntime/ExecutorHitlTest.php` because
 * they need a real {@see \Waaseyaa\AI\Agent\Repository\AgentRunRepository}
 * + {@see \Waaseyaa\AI\Agent\Repository\AgentAuditLogRepository} backed
 * by an in-memory SQLite database. Recreating those at the unit-test
 * layer would be a less faithful test surface than the integration
 * harness already in place.
 *
 * This test exists so the dead-code detector + suite runner keep the
 * class visible while the rest of the surface is exercised at the
 * integration boundary.
 */
#[CoversNothing]
final class AgentExecutorTest extends TestCase
{
    #[Test]
    public function classExistsAndIsFinal(): void
    {
        $ref = new \ReflectionClass(AgentExecutor::class);
        self::assertTrue($ref->isFinal());
        self::assertTrue($ref->hasMethod('executeRun'));
        self::assertTrue($ref->hasMethod('executeTool'));
    }
}

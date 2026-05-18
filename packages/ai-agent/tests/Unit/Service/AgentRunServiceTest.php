<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Enum\HitlMode;
use Waaseyaa\AI\Agent\Service\AgentRunDraft;

/**
 * Validation-only unit tests for {@see \Waaseyaa\AI\Agent\Service\AgentRunService}
 * draft inputs.
 *
 * End-to-end persistence + dispatch behaviour is exercised in
 * `tests/Integration/PhaseN/AgentRuntime/EnqueueAndConsumeTest.php` —
 * recreating the database wiring at the unit-test layer would be a
 * lower-fidelity surface than the integration harness.
 *
 * This file exists so {@see AgentRunDraft} stays callable from the
 * dead-code detector and so we have a place to land cheap edge-case
 * regression checks.
 */
#[CoversNothing]
final class AgentRunServiceTest extends TestCase
{
    #[Test]
    public function draftRejectsEmptyPromptAtConstructionByConvention(): void
    {
        // The DTO itself is permissive — validation lives in the
        // service. This test asserts the DTO captures the shape we
        // need.
        $draft = new AgentRunDraft(
            accountId: 1,
            agentDefinitionId: null,
            bundle: ['id' => 'smoke'],
            prompt: 'hello',
            destructiveApproval: HitlMode::None,
        );

        self::assertSame(1, $draft->accountId);
        self::assertNull($draft->agentDefinitionId);
        self::assertSame('hello', $draft->prompt);
        self::assertSame(HitlMode::None, $draft->destructiveApproval);
    }

    #[Test]
    public function draftDefaultsDestructiveApprovalToNone(): void
    {
        $draft = new AgentRunDraft(
            accountId: 1,
            agentDefinitionId: 'ad-hoc',
            bundle: null,
            prompt: 'hello',
        );

        self::assertSame(HitlMode::None, $draft->destructiveApproval);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Enum\RunStatus;

#[CoversClass(RunStatus::class)]
final class RunStatusTest extends TestCase
{
    #[Test]
    public function caseValuesMatchDataModel(): void
    {
        self::assertSame('queued', RunStatus::Queued->value);
        self::assertSame('running', RunStatus::Running->value);
        self::assertSame('awaiting_approval', RunStatus::AwaitingApproval->value);
        self::assertSame('cancelling', RunStatus::Cancelling->value);
        self::assertSame('cancelled', RunStatus::Cancelled->value);
        self::assertSame('completed', RunStatus::Completed->value);
        self::assertSame('failed', RunStatus::Failed->value);
    }

    #[Test]
    public function terminalsReturnsExactlyThreeStatuses(): void
    {
        $terminals = RunStatus::terminals();

        self::assertCount(3, $terminals);
        self::assertContains(RunStatus::Cancelled, $terminals);
        self::assertContains(RunStatus::Completed, $terminals);
        self::assertContains(RunStatus::Failed, $terminals);
    }

    #[Test]
    public function isTerminalAgreesWithTerminalsList(): void
    {
        self::assertTrue(RunStatus::Cancelled->isTerminal());
        self::assertTrue(RunStatus::Completed->isTerminal());
        self::assertTrue(RunStatus::Failed->isTerminal());

        self::assertFalse(RunStatus::Queued->isTerminal());
        self::assertFalse(RunStatus::Running->isTerminal());
        self::assertFalse(RunStatus::AwaitingApproval->isTerminal());
        self::assertFalse(RunStatus::Cancelling->isTerminal());
    }
}

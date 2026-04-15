<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Tests\Unit\Cost;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Observability\Cost\BudgetManager;
use Waaseyaa\AI\Observability\Cost\CostTracker;
use Waaseyaa\AI\Observability\Value\BudgetDecision;

#[CoversClass(BudgetManager::class)]
final class BudgetManagerTest extends TestCase
{
    #[Test]
    public function deniesWhenPerRequestLimitExceeded(): void
    {
        $mgr = new BudgetManager($this->fakeTracker(0.0), dailyLimitUsd: 100.0, perRequestLimitUsd: 1.0);
        self::assertSame(BudgetDecision::DENY, $mgr->check(2.0));
    }

    #[Test]
    public function deniesWhenDailyLimitExceeded(): void
    {
        $mgr = new BudgetManager($this->fakeTracker(95.0), dailyLimitUsd: 100.0, perRequestLimitUsd: 10.0);
        self::assertSame(BudgetDecision::DENY, $mgr->check(8.0));
    }

    #[Test]
    public function warnsAtEightyPercent(): void
    {
        $mgr = new BudgetManager($this->fakeTracker(75.0), dailyLimitUsd: 100.0, perRequestLimitUsd: 10.0);
        self::assertSame(BudgetDecision::WARN, $mgr->check(6.0));
    }

    #[Test]
    public function allowsWhenUnderThresholds(): void
    {
        $mgr = new BudgetManager($this->fakeTracker(10.0), dailyLimitUsd: 100.0, perRequestLimitUsd: 10.0);
        self::assertSame(BudgetDecision::ALLOW, $mgr->check(1.0));
    }

    private function fakeTracker(float $dailyTotal): CostTracker
    {
        return new class($dailyTotal) extends CostTracker {
            public function __construct(private readonly float $total) {}

            public function totalForTrace(string $traceUuid): float
            {
                return 0.0;
            }

            public function totalForPeriod(\DateTimeInterface $from, \DateTimeInterface $to): float
            {
                return $this->total;
            }
        };
    }
}

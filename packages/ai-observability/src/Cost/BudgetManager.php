<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Cost;

use Waaseyaa\AI\Observability\Value\BudgetDecision;

/**
 * @api
 */
final class BudgetManager
{
    private const WARN_RATIO = 0.8;

    public function __construct(
        private readonly CostTracker $tracker,
        private readonly float $dailyLimitUsd,
        private readonly float $perRequestLimitUsd,
    ) {}

    public function check(float $projectedAdditionalUsd): BudgetDecision
    {
        if ($projectedAdditionalUsd > $this->perRequestLimitUsd) {
            return BudgetDecision::DENY;
        }

        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $dailyTotal = $this->tracker->totalForPeriod($today, $tomorrow);

        $projectedTotal = $dailyTotal + $projectedAdditionalUsd;

        if ($projectedTotal > $this->dailyLimitUsd) {
            return BudgetDecision::DENY;
        }

        if ($projectedTotal > $this->dailyLimitUsd * self::WARN_RATIO) {
            return BudgetDecision::WARN;
        }

        return BudgetDecision::ALLOW;
    }
}

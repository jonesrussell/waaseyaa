<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Value;

enum BudgetDecision: string
{
    case ALLOW = 'allow';
    case WARN = 'warn';
    case DENY = 'deny';
}

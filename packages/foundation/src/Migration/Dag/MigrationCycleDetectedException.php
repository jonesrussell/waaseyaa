<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration\Dag;

/**
 * Raised when {@see MigrationGraph::topologicalOrder()} cannot produce
 * a linear order because the dependency graph contains a cycle.
 *
 * The exception carries one concrete cycle (the algorithm finds and
 * reports the first one it walks; there may be more in the residual
 * graph). The diagnostic code `MIGRATION_CYCLE` is part of the operator
 * surface — runbooks and CI greps match on the string.
 */
final class MigrationCycleDetectedException extends \RuntimeException
{
    public const DIAGNOSTIC_CODE = 'MIGRATION_CYCLE';

    /**
     * @param list<string> $cycle node ids forming the cycle, in walk
     *                            order. The first id appears once at the
     *                            start; the cycle closes implicitly.
     */
    public function __construct(public readonly array $cycle)
    {
        parent::__construct(sprintf(
            'Migration dependency graph contains a cycle: %s. Break the cycle by removing one of the dependency edges.',
            implode(' -> ', [...$cycle, $cycle[0] ?? '?']),
        ));
    }

    public function diagnosticCode(): string
    {
        return self::DIAGNOSTIC_CODE;
    }
}

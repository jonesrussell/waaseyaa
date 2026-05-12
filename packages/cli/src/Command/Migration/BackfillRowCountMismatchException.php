<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migration;

/**
 * Thrown when the post-backfill row count differs from the pre-backfill row count.
 *
 * The migration transaction will roll back when this exception is thrown,
 * leaving the database in its original state.
 *
 * @api
 */
final class BackfillRowCountMismatchException extends \RuntimeException
{
    /**
     * @api
     */
    public function __construct(
        public readonly string $table,
        public readonly int $expected,
        public readonly int $actual,
    ) {
        parent::__construct(sprintf(
            'BackfillHelper: row count mismatch in table "%s" after backfill — expected %d rows, got %d. '
            . 'Migration will be rolled back to preserve data integrity.',
            $table,
            $expected,
            $actual,
        ));
    }
}

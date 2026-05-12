<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Exception;

use Waaseyaa\Entity\EntityInterface;

/**
 * @api
 *
 * Thrown when backend fan-out partially fails during a save or delete operation.
 *
 * The coordinator catches the first backend throwable, partitions backends into
 * committed (completed before the throw) and uncommitted (the failing backend
 * plus any that were not yet attempted), then throws this exception.
 *
 * {@see AfterSaveEvent} / {@see AfterDeleteEvent} are NOT dispatched when this
 * exception is thrown. A structured log line is emitted with outcome=partial_save.
 *
 * ## Recovery
 * Callers MAY inspect {@see $committedBackends} to understand partial state.
 * Recovery (rollback, retry, reconciliation) is application-level responsibility.
 * The coordinator does NOT attempt rollback.
 *
 * ## Why $errorCode, not $code
 * `\Exception::$code` is a non-readonly `protected int`. PHP forbids redeclaring it
 * with a type in any subclass ("Type of X::$code must be omitted to match the parent
 * definition"), so a typed `public readonly string $code` is impossible. `$errorCode`
 * is therefore the canonical name on the stable surface (spec §6.5, contract).
 */
final class PartialSaveException extends \RuntimeException
{
    /**
     * @param EntityInterface $entity           The entity for which the operation partially failed.
     * @param \Throwable      $causedBy         The original throwable from the failing backend.
     * @param string[]        $committedBackends Backend ids that completed successfully before the failure.
     * @param string[]        $uncommittedBackends Backend ids that did not run (including the failing one).
     * @param string          $errorCode        Machine-readable error code — always 'PARTIAL_SAVE'.
     */
    public function __construct(
        public readonly EntityInterface $entity,
        public readonly \Throwable $causedBy,
        public readonly array $committedBackends,
        public readonly array $uncommittedBackends,
        public readonly string $errorCode = 'PARTIAL_SAVE',
    ) {
        $committedCount = count($committedBackends);
        $uncommittedCount = count($uncommittedBackends);

        parent::__construct(
            sprintf(
                'Partial save: %d backend(s) committed [%s], %d backend(s) uncommitted [%s]. Caused by: %s — %s',
                $committedCount,
                implode(', ', $committedBackends),
                $uncommittedCount,
                implode(', ', $uncommittedBackends),
                $causedBy::class,
                $causedBy->getMessage(),
            ),
            0,
            $causedBy,
        );
    }
}

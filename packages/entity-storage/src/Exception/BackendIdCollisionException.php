<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Exception;

/**
 * @api
 *
 * Raised at boot when two providers attempt to register backends under the
 * same id, or when a third-party provider attempts to register a reserved id
 * (sql-blob, sql-column, vector).
 *
 * When $firstFqcn is null the collision is a reserved-id misuse: the id is
 * owned by the framework and no specific prior registrant FQCN exists.
 *
 * {@see \Waaseyaa\EntityStorage\Backend\BackendRegistrar}
 * {@see \Waaseyaa\EntityStorage\Backend\ReservedBackendIds}
 */
final class BackendIdCollisionException extends \RuntimeException
{
    public readonly string $backendId;
    public readonly ?string $firstFqcn;
    public readonly string $secondFqcn;

    public function __construct(string $id, ?string $firstFqcn, string $secondFqcn)
    {
        $this->backendId = $id;
        $this->firstFqcn = $firstFqcn;
        $this->secondFqcn = $secondFqcn;

        if ($firstFqcn === null) {
            $message = sprintf(
                "Backend id '%s' is reserved by the framework; third-party provider '%s' MUST register under a different id.",
                $id,
                $secondFqcn,
            );
        } else {
            $message = sprintf(
                'Backend id collision: id "%s" is already registered by "%s"; "%s" cannot also claim it.',
                $id,
                $firstFqcn,
                $secondFqcn,
            );
        }

        parent::__construct($message);
    }
}

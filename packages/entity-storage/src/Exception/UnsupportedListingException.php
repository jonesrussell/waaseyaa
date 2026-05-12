<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Exception;

/**
 * @api
 *
 * Reserved for ADR 015 forward-compatibility: raised when a backend declares
 * it cannot satisfy a listing (paginated list / cursor-based browse) for a
 * given entity type.
 *
 * Shape mirrors {@see UnsupportedQueryException} so callers can handle both
 * with a common catch block if desired.
 *
 * WP06 reserves this class. The validator does not throw it yet — that
 * capability belongs to the listing contract introduced in a later WP.
 *
 * Message format: "Backend {$backendId} cannot satisfy listing for entity type {$entityTypeId}: {$reason}"
 */
final class UnsupportedListingException extends \LogicException
{
    public readonly string $backendId;
    public readonly string $entityTypeId;
    public readonly string $reason;

    public function __construct(string $backendId, string $entityTypeId, string $reason)
    {
        $this->backendId = $backendId;
        $this->entityTypeId = $entityTypeId;
        $this->reason = $reason;

        parent::__construct(
            sprintf(
                'Backend %s cannot satisfy listing for entity type %s: %s',
                $backendId,
                $entityTypeId,
                $reason,
            ),
        );
    }
}

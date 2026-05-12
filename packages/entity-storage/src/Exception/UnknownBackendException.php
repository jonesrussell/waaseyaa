<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Exception;

/**
 * @api
 *
 * Thrown by {@see \Waaseyaa\EntityStorage\BackendResolver} when a backend id
 * referenced by a field definition or entity type is not registered.
 *
 * Unlike a silent fallback, an explicit exception surfaces misconfigured field
 * routing at the earliest possible point — coordinator construction or first
 * use — rather than silently writing to the wrong backend.
 */
final class UnknownBackendException extends \RuntimeException
{
    public function __construct(string $backendId, string $context)
    {
        parent::__construct(
            sprintf(
                'Backend id "%s" is not registered. Context: %s',
                $backendId,
                $context,
            ),
        );
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Exception;

use RuntimeException;

/**
 * Thrown by {@code ListingDefinitionRegistry::get()} when the requested
 * listing id is not registered (FR-055).
 *
 * @api
 */
final class UnknownListingException extends RuntimeException
{
    public function __construct(public readonly string $listingId)
    {
        parent::__construct(sprintf('Unknown listing "%s".', $listingId));
    }
}

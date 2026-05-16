<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@code ListingDefinitionValidator} or the resolver when a
 * declared listing references a field/operator/backend combination that
 * cannot be satisfied (FR-054).
 *
 * Carries the listing id plus the field that failed validation (if any)
 * and a human-readable reason so callers can surface meaningful errors
 * without losing context.
 *
 * @api
 */
final class UnsupportedListingException extends RuntimeException
{
    public function __construct(
        public readonly string $listingId,
        public readonly ?string $fieldName,
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        $message = sprintf(
            'Unsupported listing "%s"%s: %s',
            $listingId,
            $fieldName !== null ? sprintf(' (field "%s")', $fieldName) : '',
            $reason,
        );
        parent::__construct($message, 0, $previous);
    }
}

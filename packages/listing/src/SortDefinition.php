<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

use InvalidArgumentException;

/**
 * Immutable sort declaration.
 *
 * Pure data carrier — field-existence and supportsQuery checks are
 * deferred to {@code ListingDefinitionValidator} (WP10).
 *
 * @api
 */
final readonly class SortDefinition
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::ASC,
    ) {
        if ($this->field === '') {
            throw new InvalidArgumentException('SortDefinition: $field must be non-empty.');
        }
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

use InvalidArgumentException;

/**
 * Pagination metadata returned alongside a {@see ListingResult}.
 *
 * Pure data carrier. {@code $totalRows} and {@code $totalPages} may be
 * null when the listing is configured with `approximateTotal === true`
 * (FR-027 fast-path).
 *
 * @api
 */
final readonly class Pagination
{
    /**
     * Constructor parameter types are intentionally plain `int`/`?int` so
     * that the runtime invariants below are exercised by callers (and by
     * the negative-path unit tests). The narrower contract — `page` and
     * `pageSize` positive, `totalRows >= 0`, paired nullability — is
     * documented on the data-model contract.
     */
    public function __construct(
        public int $page,
        public int $pageSize,
        public ?int $totalRows,
        public ?int $totalPages,
        public bool $hasPrev,
        public bool $hasNext,
    ) {
        if ($this->page < 1) {
            throw new InvalidArgumentException('Pagination: $page must be a positive integer (1-indexed).');
        }
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('Pagination: $pageSize must be a positive integer.');
        }
        if ($this->totalRows !== null && $this->totalRows < 0) {
            throw new InvalidArgumentException('Pagination: $totalRows must be >= 0 when set.');
        }
        if ($this->totalPages !== null && $this->totalPages < 1) {
            throw new InvalidArgumentException('Pagination: $totalPages must be a positive integer when set.');
        }
        if (($this->totalRows === null) !== ($this->totalPages === null)) {
            throw new InvalidArgumentException(
                'Pagination: $totalRows and $totalPages must either both be set or both be null.',
            );
        }
    }
}

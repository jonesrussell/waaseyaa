<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

/**
 * Result of a listing resolution: rows plus pagination + cache metadata.
 *
 * Only the four constructor-promoted properties are stable surface
 * (FR-022). Internal storage shape is not.
 *
 * @api
 */
final readonly class ListingResult
{
    /**
     * @param iterable<mixed>             $rows          iterable of entity instances
     * @param list<non-empty-string>      $cacheTags
     * @param list<non-empty-string>      $cacheContexts
     */
    public function __construct(
        public iterable $rows,
        public Pagination $pagination,
        public array $cacheTags,
        public array $cacheContexts,
    ) {}
}
